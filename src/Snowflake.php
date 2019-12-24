<?php
/**
 * 基于Twitter的雪花算法改造，分布式全局唯一ID生成器
 *
 * @author  Giles <giles.wang@aliyun.com|giles.wang@qq.com>
 * @date    2019/12/24 14:37
 *
 */
namespace Giles\Library;

/**
 *
 * Class Snowflake
 * 分布式雪花算法ID生成器,组成<毫秒级时间戳+机器ip+进程id+序列号>
 * 长度最长为64位bit,各bit位含义如下：
 * . 最高1bit位为符号位,始终为0代表正数.
 * . 高41bit位是时间序列(有简单排序作用),可精确到毫秒级别,41位无符号长度可以用69+年
 * . 接下来是10bit位是机器IP低10位,可以支持最多1023个机器节点
 * . 再下来10bit位的当前处理进程标识,10位的长度最多支持1023个机器进程
 * . 最后的2bit位的计数序列号,序列号即序列自增id,可以支持同一节点的同一进程同一毫秒生成4个ID序号
 *
 * @package WeberGiles\Favorites
 *
 * @author  Giles <giles.wang@aliyun.com|giles.wang@qq.com>
 * @date    2019/12/24 14:37
 */
final class Snowflake
{
    /** @var int 秒时间戳bit长度, 41位可以表示$2^{41}-1$个数字, 转化成单位年则是$(2^{41}-1) / (1000 * 60 * 60 * 24 * 365) = 69$年 */
    private const TIMESTAMP_BITS = 41;
    /** @var int 机器节点位的位长度, 10bit可支持1023个机器节点 */
    private const MACHINE_BITS = 10;
    /** @var int 进程的位bit长度,可支持1023个进程 */
    private const PROCESS_BITS = 10;
    /** @var int 计数序列号位数,2个bit可支持每秒生成4(0,1,2,3)个序列号 */
    private const SEQUENCE_BITS = 2;
    /** @var int 时间戳bit位偏移量 */
    private const TIMESTAMP_BIT_OFFSET = self::MACHINE_BITS + self::PROCESS_BITS + self::SEQUENCE_BITS;
    /** @var int 机器ID bit位偏移量 */
    private const MACHINE_BIT_OFFSET = self::PROCESS_BITS + self::SEQUENCE_BITS;
    /** @var int 进程ID bit位偏移量 */
    private const PROCESS_BITS_OFFSET = self::SEQUENCE_BITS;
    /** @var int 最大的毫秒时间差量值,可表示 */
    private const MAX_MILLISECOND_TIMESTAMP = (-1 ^ (-1 << self::TIMESTAMP_BITS));
    /** @var int 最大的机器ID(这个移位算法可以很快的计算出几位二进制数所能表示的最大十进制数) */
    private const MAX_MACHINE_ID = (-1 ^ (-1 << self::MACHINE_BITS));
    /** @var int 最大的进程ID */
    private const MAX_PROCESS_ID = (-1 ^ (-1 << self::PROCESS_BITS));
    /** @var int 最大的序号编号ID */
    private const MAX_SEQUENCE_ID = (-1 ^ (-1 << self::SEQUENCE_BITS));
    /**
     * @var int
     * 起始偏移时间戳(2017-01-01 00:00:00的毫秒时间戳)
     * 该时间一定要小于第一个id生成的时间,且尽量大(影响算法最终的有效可用时间)
     * 有效可用时间 = self::EPOCH_OFFSET + (-1 ^ (-1 << self::TIMESTAMP_BITS))
     */
    private const EPOCH_OFFSET = 1483200000000;
    /** @var int 最后一次生成ID时的毫秒时间戳 */
    private static $lastMillisecondTimeStamp = 0;
    /** @var int 当前机器ID 根据ip地址计算 */
    private static $machineId;
    /** @var int 当前进程ID */
    private static $processId;
    /** @var int 起始的序号编号 */
    private static $sequenceId = 0;


    /**
     * 生成全局唯一ID
     *
     * @return string
     * @author Giles <giles.wang@aliyun.com|giles.wang@qq.com>
     * @date   2019/12/24 14:35
     */
    public static function uniqueId(): string
    {
        $machineId = self::getMachineId();
        $processId = self::getProcessId();
        $sequence = self::getSequence();
        $pastTime = self::getPastTime();

        return (($pastTime << self::TIMESTAMP_BIT_OFFSET) |
                ($machineId << self::MACHINE_BIT_OFFSET) |
                ($processId << self::PROCESS_BITS_OFFSET) |
                $sequence) & PHP_INT_MAX;
    }

    /**
     * 获取机器ID(机器IP的低self::MACHINE_BITS位的值)
     *
     * @return int
     * @author Giles <giles.wang@aliyun.com|giles.wang@qq.com>
     * @date   2019/12/24 14:35
     */
    private static function getMachineId()
    {
        if (empty(self::$machineId)) {
            $hostName = gethostname();
            //将机器名的md5值中的数字取出 对1024 取模，计算机器号
            $achineId = preg_replace('/\D/s', '', md5($hostName)) % 1024;

            if (self::MAX_MACHINE_ID < $achineId) {
                throw new \RuntimeException('机器ID大于允许的最大值');
            }
            self::$machineId = $achineId;
        }

        return self::$machineId;
    }

    /**
     * 获取当前执行进程的PID(低14位值)
     *
     * @return int
     * @author Giles <giles.wang@aliyun.com|giles.wang@qq.com>
     * @date   2019/12/24 14:35
     */
    private static function getProcessId(): int
    {
        if (empty(self::$processId)) {
            $processId = getmypid();
            if (false === $processId) {
                throw new \RuntimeException('获取进程PID失败');
            }

            // 截取进程ID二进制值的低self::PROCESS_BITS位值
            $processIdLowBit = $processId & self::MAX_PROCESS_ID;
            if (self::MAX_PROCESS_ID < $processIdLowBit) {
                throw new \RuntimeException('进程ID大于允许的最大值');
            }
            self::$processId = $processIdLowBit;
        }

        return self::$processId;
    }

    /**
     * 获取计数序列值
     *
     * @return int
     * @author Giles <giles.wang@aliyun.com|giles.wang@qq.com>
     * @date   2019/12/24 14:34
     */
    private static function getSequence(): int
    {
        $currentMicroTimeStamp = self::getCurrentMicrosecond();
        // 当前的时间比上次的时间还小
        if ($currentMicroTimeStamp < self::$lastMillisecondTimeStamp) {
            throw new \RuntimeException('生成ID所依靠的时间回拨了');
        }

        //上次生成ID时所用的时间戳和本次生成的一样,那就自增序列号
        if ($currentMicroTimeStamp == self::$lastMillisecondTimeStamp) {
            $sequence = ++self::$sequenceId;
            //如果序列达到生成最大值,则等待下一毫秒重新生成并重置序列号
            if ($sequence > self::MAX_SEQUENCE_ID) {
                do {
                    $currentMicroTimeStamp = self::getCurrentMicrosecond();
                } while ($currentMicroTimeStamp <= self::$lastMillisecondTimeStamp);
                self::$sequenceId = 0;
                $sequence = self::$sequenceId;
            }
        } else {
            //高并发下会出现重复的ID  防止重复增加随机数
            self::$sequenceId = mt_rand(0, 3);
            $sequence = self::$sequenceId;
        }

        self::$lastMillisecondTimeStamp = $currentMicroTimeStamp;

        return $sequence;
    }

    /**
     * 获取已经经过的时间的毫秒数
     *
     * @return int
     * @author Giles <giles.wang@aliyun.com|giles.wang@qq.com>
     * @date   2019/12/24 14:34
     */
    private static function getPastTime(): int
    {
        $pastMillisecond = self::$lastMillisecondTimeStamp - self::EPOCH_OFFSET;
        if ($pastMillisecond > self::MAX_MILLISECOND_TIMESTAMP) {
            throw new \RuntimeException('时间已经超越唯一序列设计规格');
        }

        // 截取时间二进制值的低self::TIMESTAMP_BITS位
        $pastMicroSecondLowBit = $pastMillisecond & self::MAX_MILLISECOND_TIMESTAMP;

        return $pastMicroSecondLowBit;
    }

    /**
     * 获取当前Unix时间戳毫秒数
     * 因为PHP实现原因,返回的毫妙最后两位始终是0,所以实践使用中将最后两位去掉以增加合理的有效位数
     *
     * @return int
     * @author Giles <giles.wang@aliyun.com|giles.wang@qq.com>
     * @date   2019/12/24 14:34
     */
    private static function getCurrentMicrosecond(): int
    {
        return (int)(microtime(true) * 1000);
    }
}
