<?php
/**
 * 中国大陆居民身份证验证
 *
 * 可以获得身份证的性别, 出生年月日, 地区, 属相, 星座
 * @author Giles
 *
 */
namespace Giles\Library;

class IdCard
{
    protected $idLength;     //身份证长度
    protected $areasCode;    //区县编码
    protected $cityCode;     //市编码
    protected $provinceCode; //省编码
    protected $idCard;       //身份证号

    /**
     * @var array 加权因子
     */
    protected $salt = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];

    /**
     * @var array 校验码
     */
    protected $checksum = [1, 0, 'X', 9, 8, 7, 6, 5, 4, 3, 2];

    /**
     * 验证居民身份证合法性 合法返回所属信息 不合法返回false
     *
     * @param $idCard
     * @return array|bool
     */
    public function verification($idCard)
    {
        $this->idCard       = trim($idCard);
        $this->idLength     = strlen($idCard);
        $this->provinceCode = substr($this->idCard, 0, 2);
        $this->cityCode     = substr($this->idCard, 0, 4);
        $this->areasCode    = substr($this->idCard, 0, 6);

        // 格式校验、生日校验、校验码校验
        if (!$this->checkFormat() || !$this->checkBirthday() || !$this->checkLastCode() || !$this->checkProvince()) {
            return false;
        }

        return [
            'idCard'   => $this->idCard,
            'province' => $this->getProvince(),
            'city'     => $this->getCity(),
            'areas'    => $this->getAreas(),
            'gender'   => $this->getGender(),
            'age'      => $this->getAge(),
            'birthday' => $this->getBirthday(),
            'star'     => $this->getStar(),
            'zodiac'   => $this->getZodiac(),
        ];
    }

    /**
     * 随机生成一个身份证号
     *
     * @return string
     */
    public function generate() :string
    {
        $path = dirname(__FILE__);
        $areasJson = file_get_contents($path.'/data/IdCard_areas.json');
        $areas = array_rand(json_decode($areasJson, true), 1);

        $year = mt_rand(date('Y') - 100, date('Y'));
        $month = mt_rand(1, 12);
        if ($month == 2) {
            $day = mt_rand(1, 28);
        } elseif (in_array($month, [4, 6, 9, 11])) {
            $day = mt_rand(1, 30);
        } else {
            $day = mt_rand(1, 31);
        }
        $seq = sprintf('%03d', mt_rand(0, 999));
        $idCard = $areas . $year . sprintf('%02d%02d', $month, $day) . $seq;

        return $idCard. $this->generateLastChar($idCard);
    }

    /**
     * 计算最后一位
     * @param $idCard
     * @return string
     */
    private function generateLastChar($idCard): string
    {
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $sum += $idCard[$i] * $this->salt[$i];
        }
        return $this->checksum[$sum % 11];
    }

    /**
     * 检查号码格式
     *
     * @return bool
     */
    private function checkFormat()
    {
        if (! preg_match('/^([\d]{17}[xX\d]|[\d]{15})$/', $this->idCard)) {
            return false;
        }
        return true;
    }

    /**
     * 检查出生年月1900年以后
     *
     * @return boolean
     */
    protected function checkBirthday()
    {
        if ($this->idLength == 18) {
            $birthday = substr($this->idCard, 6, 4) .'-'. substr($this->idCard, 10, 2)
                .'-'. substr($this->idCard, 12, 2);
        } else {
            $birthday = '19'. substr($this->idCard, 6, 2) .'-'. substr($this->idCard, 8, 2)
                .'-'. substr($this->idCard, 10, 2);
        }

        $rPattern = <<<'TAG'
/^(([0-9]{2})|(19[0-9]{2})|(20[0-9]{2}))-((0[1-9]{1})|(1[012]{1}))-((0[1-9]{1})|(1[0-9]{1})|(2[0-9]{1})|3[01]{1})$/
TAG;
        if (preg_match($rPattern, $birthday, $arr)) {
            return true;
        }
        return false;
    }

    /**
     * 校验最后一位校验码
     *
     * @return boolean
     */
    protected function checkLastCode()
    {
        if ($this->idLength == 15) {
            return true;
        }
        $sum = 0;
        $number = (string) $this->idCard;
        for ($i = 0; $i < 17; $i ++) {
            $sum += $number{$i} * $this->salt{$i};
        }
        $seek = $sum % 11;
        if ((string) $this->checksum[$seek] !== strtoupper($number{17})) {
            return false;
        }
        return true;
    }

    /**
     * 校验地区是否合法
     *
     * @return boolean
     */
    protected function checkProvince()
    {
        $path = dirname(__FILE__);
        $provinceJson = file_get_contents($path.'/data/IdCard_province.json');

        $provinceList = json_decode($provinceJson, true);
        if (!isset($provinceList[$this->provinceCode])) {
            return false;
        }

        return true;
    }

    /**
     * 根据身份证号，自动返回对应的省
     *
     * @return string
     *
     */
    private function getProvince()
    {
        $path = dirname(__FILE__);
        $provinceJson = file_get_contents($path.'/data/IdCard_province.json');

        $provinceList = json_decode($provinceJson, true);
        if (isset($provinceList[$this->provinceCode])) {
            return $provinceList[$this->provinceCode];
        }
    }

    /**
     * 根据身份证号，自动返回对应的 市
     *
     * @return string
     */
    private function getCity()
    {
        $path = dirname(__FILE__);
        $cityJson = file_get_contents($path.'/data/IdCard_city.json');
        $cityData = json_decode($cityJson, true);

        if (isset($cityData[$this->cityCode])) {
            return $cityData[$this->cityCode];
        } else {
            $cityOldJson = file_get_contents($path.'/data/IdCard_cityOld.json');
            $cityOldData = json_decode($cityOldJson, true);
            if (isset($cityOldData[$this->cityCode])) {
                return $cityOldData[$this->cityCode];
            }
        }
    }

    /**
     * 根据身份证号， 获取区县
     *
     * @return mixed
     */
    private function getAreas()
    {
        $path = dirname(__FILE__);
        $areasJson = file_get_contents($path.'/data/IdCard_areas.json');
        $areasData = json_decode($areasJson, true);

        if (isset($areasData[$this->areasCode])) {
            return $areasData[$this->areasCode];
        } else {
            $areasOldJson = file_get_contents($path.'/data/IdCard_areasOld.json');
            $areasOldData = json_decode($areasOldJson, true);
            if (isset($areasOldData[$this->areasCode])) {
                return $areasOldData[$this->areasCode];
            }
        }
    }

    /**
     * 获取证件号所属的属相
     *
     * @return string
     */
    private function getZodiac()
    {
        $start = 1901;
        if ($this->idLength == 18) {
            $end   = (int)substr($this->idCard, 6, 4);
        } else {
            $end   = (int)19 .substr($this->idCard, 6, 2);
        }
        $diff   = ($start - $end) % 12;
        $zodiac = '';
        if ($diff == 1 || $diff == -11) {
            $zodiac = "鼠";
        }
        if ($diff == 0) {
            $zodiac = "牛";
        }
        if ($diff == 11 || $diff == -1) {
            $zodiac = "虎";
        }
        if ($diff == 10 || $diff == -2) {
            $zodiac = "兔";
        }
        if ($diff == 9 || $diff == -3) {
            $zodiac = "龙";
        }
        if ($diff == 8 || $diff == -4) {
            $zodiac = "蛇";
        }
        if ($diff == 7 || $diff == -5) {
            $zodiac = "马";
        }
        if ($diff == 6 || $diff == -6) {
            $zodiac = "羊";
        }
        if ($diff == 5 || $diff == -7) {
            $zodiac = "猴";
        }
        if ($diff == 4 || $diff == -8) {
            $zodiac = "鸡";
        }
        if ($diff == 3 || $diff == -9) {
            $zodiac = "狗";
        }
        if ($diff == 2 || $diff == -10) {
            $zodiac = "猪";
        }
        return $zodiac;
    }

    /**
     * 获取证件号性别
     *
     * @return string
     */
    private function getGender()
    {
        if ($this->idLength == 18) {
            $gender = $this->idCard{16};
        } else {
            $gender = $this->idCard{14};
        }

        return $gender % 2 === 0 ? '女' : '男';
    }

    /**
     * 根据身份证计算年龄
     *
     */
    private function getAge()
    {
        $date   = strtotime(substr($this->idCard, 6, 8));
        $diff   = floor((time() - $date) / 86400 / 365);

        $age = strtotime(substr($this->idCard, 6, 8) . ' +' . $diff . 'years') > time()
            ? ($diff + 1)
            : $diff;

        return $age < 0 ? 0 : $age;
    }

    /**
     * 获取证件号所属星座
     *
     * @return string
     */
    private function getStar()
    {
        if ($this->idLength == 18) {
            $star   = substr($this->idCard, 10, 4);
        } else {
            $star   = substr($this->idCard, 8, 4);
        }

        $month  = (int)substr($star, 0, 2);
        $day    = (int)substr($star, 2);

        $star = '';
        if (($month == 1 && $day <= 21) || ($month == 2 && $day <= 19)) {
            $star = "水瓶座";
        } elseif (($month == 2 && $day > 20) || ($month == 3 && $day <= 20)) {
            $star = "双鱼座";
        } elseif (($month == 3 && $day > 20) || ($month == 4 && $day <= 20)) {
            $star = "白羊座";
        } elseif (($month == 4 && $day > 20) || ($month == 5 && $day <= 21)) {
            $star = "金牛座";
        } elseif (($month == 5 && $day > 21) || ($month == 6 && $day <= 21)) {
            $star = "双子座";
        } elseif (($month == 6 && $day > 21) || ($month == 7 && $day <= 22)) {
            $star = "巨蟹座";
        } elseif (($month == 7 && $day > 22) || ($month == 8 && $day <= 23)) {
            $star = "狮子座";
        } elseif (($month == 8 && $day > 23) || ($month == 9 && $day <= 23)) {
            $star = "处女座";
        } elseif (($month == 9 && $day > 23) || ($month == 10 && $day <= 23)) {
            $star = "天秤座";
        } elseif (($month == 10 && $day > 23) || ($month == 11 && $day <= 22)) {
            $star = "天蝎座";
        } elseif (($month == 11 && $day > 22) || ($month == 12 && $day <= 21)) {
            $star = "射手座";
        } elseif (($month == 12 && $day > 21) || ($month == 1 && $day <= 20)) {
            $star = "魔羯座";
        }
        return $star;
    }

    /**
     * 获取证件号出生日期
     *
     * @return string
     */
    private function getBirthday()
    {
        if ($this->idLength == 18) {
            return substr($this->idCard, 6, 4) .'-'. substr($this->idCard, 10, 2)
                .'-'. substr($this->idCard, 12, 2);
        } else {
            return '19'. substr($this->idCard, 6, 2) .'-'. substr($this->idCard, 8, 2)
                .'-'. substr($this->idCard, 10, 2);
        }
    }
}
