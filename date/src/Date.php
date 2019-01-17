<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2018 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Date;

class Date
{

    /**
     * get current timezone offset time
     *
     * @param boolean $returnSec
     * @return int
     */
    public static function getTimezoneOffset($returnSec = false)
    {
        if ($returnSec) {
            return date('Z');
        }
        $number = date('P');
        list($hour, $min) = explode(':', $number);
        $sign = substr($number, 0, 1);
        $hpad = substr($hour, 1, 1) * 10;
        $mpad = substr($min, 0, 1) * 10;

        $h = $hpad + substr($number, 2);
        $m = $mpad + substr($min, 1) / 60;
        return $sign . ($h + $m);
    }

    /**
     * set timezone, support time offset hours
     * example: +8
     *          ETC/GMT-8
     *
     * @param string $zone
     */
    public static function setTimeZone($zone)
    {
        if (is_numeric($zone)) {
            $zone = (int) $zone * -1;
            $zone = 'ETC/GMT' . ($zone > 0 ? "+$zone" : "$zone");
        }
        date_default_timezone_set($zone);
    }

    public static function getWeek()
    {
        return date('w');
    }

    public static function getMonthDay()
    {
        return date('j');
    }

    public static function getDay()
    {
        return date('z');
    }

    public static function isLeap($year = null)
    {
        if($year) {
            return date('L') == 1 ? true : false;
        }
        $time = self::mktime("1 1 1 1 1 $year");
        return date('L', $time);
    }

    public static function getYear()
    {
        return date('Y');
    }

    /**
     * get date
     *
     * @param string $format
     * @param int $timestamp
     * @param string $zone
     * @return string
     */
    public static function date($format, $timestamp = null, $zone = null)
    {
        if ($zone) {
            $currzone = date_default_timezone_get();
            self::setTimeZone($zone);
        }

        $timestamp = $timestamp ?? time();
        $res = date($format, $timestamp);
        self::setTimeZone($currzone);
        return $res;
    }

    /**
     * get unix time for a date
     *
     * @param string $time    number or expression,the expression cannot has space, the format is: H i s n j Y
     *                          all number:'0 0 0 2 3 2000',
     *                                     '23 23 4 1 12 1999',
     *                          have expression:'H-2 12 3 n+2 12 1999', current hors minus 2 hours and current month day add 2 days
     * @return int
     */
    public static function mktime($time)
    {
        $timepart = explode(' ', $time);
        if (\count($timepart) !== 6) {
            throw new \Exception('passed time must be "H i s n j Y" with number');
        }
        $passOrder = ['H', 'i', 's', 'n', 'j', 'Y'];
        foreach ($timepart as $i => $v) {
            if (!\is_numeric($v)) {
                if (\strpos($v, $passOrder[$i]) !== 0) {
                    throw new \Exception('only support 2 operands for simple arithmetic and first operans must be a char in "HisnjY"');
                }
                $realtime = date($passOrder[$i]);
                $operands = $v[1] == '*' && $v[2] == '*' ? \substr($v, 2) : \substr($v, 1);
                if (!\is_numeric($operands)) {
                    throw new \Exception('the second operand must be numeric');
                }
                if ($v[1] == '+') {
                    $timepart[$i] = $realtime + $operands;
                } elseif ($v[1] == '-') {
                    $timepart[$i] = $realtime - $operands;
                } elseif ($v[1] == '/') {
                    $timepart[$i] = $realtime / $operands;
                } elseif ($v[1] == '*' && $v[2] == '*') {
                    $timepart[$i] = $realtime ** $operands;
                } elseif ($v[1] == '*') {
                    $timepart[$i] = $realtime * $operands;
                } else {
                    throw new \Exception('only support operators is "+,-,*,/,**"');
                }
            }
        }
        return \mkdir($timepart[0], $timepart[1], $timepart[2], $timepart[3], $timepart[4], $timepart[5]);
    }

    /**
     * get supported timezone, lastest 2018-4
     *
     * @return array
     */
    public static function getSupportedTimezones()
    {
        return ['Africa' => ['Abidjan', 'Accra', 'Addis_Ababa', 'Algiers', 'Asmara', 'Bamako', 'Bangui', 'Banjul', 'Bissau', 'Blantyre', 'Brazzaville', 'Bujumbura', 'Cairo', 'Casablanca', 'Ceuta', 'Conakry', 'Dakar', 'Dar_es_Salaam', 'Djibouti', 'Douala', 'El_Aaiun', 'Freetown', 'Gaborone', 'Harare', 'Johannesburg', 'Juba', 'Kampala', 'Khartoum', 'Kigali', 'Kinshasa', 'Lagos', 'Libreville', 'Lome', 'Luanda', 'Lubumbashi', 'Lusaka', 'Malabo', 'Maputo', 'Maseru', 'Mbabane', 'Mogadishu', 'Monrovia', 'Nairobi', 'Ndjamena', 'Niamey', 'Nouakchott', 'Ouagadougou', 'Porto-Novo', 'Sao_Tome', 'Tripoli', 'Tunis', 'Windhoek'], 'America' => ['Adak', 'Anchorage', 'Anguilla', 'Antigua', 'Araguaina', 'Argentina' => 'Buenos_Aires', 'Catamarca', 'Cordoba', 'Jujuy', 'La_Rioja', 'Mendoza', 'Rio_Gallegos', 'Salta', 'San_Juan', 'San_Luis', 'Tucuman', 'Ushuaia', 'Aruba', 'Asuncion', 'Atikokan', 'Bahia', 'Bahia_Banderas', 'Barbados', 'Belem', 'Belize', 'Blanc-Sablon', 'Boa_Vista', 'Bogota', 'Boise', 'Cambridge_Bay', 'Campo_Grande', 'Cancun', 'Caracas', 'Cayenne', 'Cayman', 'Chicago', 'Chihuahua', 'Costa_Rica', 'Creston', 'Cuiaba', 'Curacao', 'Danmarkshavn', 'Dawson', 'Dawson_Creek', 'Denver', 'Detroit', 'Dominica', 'Edmonton', 'Eirunepe', 'El_Salvador', 'Fort_Nelson', 'Fortaleza', 'Glace_Bay', 'Godthab', 'Goose_Bay', 'Grand_Turk', 'Grenada', 'Guadeloupe', 'Guatemala', 'Guayaquil', 'Guyana', 'Halifax', 'Havana', 'Hermosillo', 'Indiana' => ['Indianapolis', 'Knox', 'Marengo', 'Petersburg', 'Tell_City', 'Vevay', 'Vincennes', 'Winamac'], 'Inuvik', 'Iqaluit', 'Jamaica', 'Juneau', 'Kentucky' => ['Louisville', 'Monticello'], 'Kralendijk,La_Paz', 'Lima', 'Los_Angeles', 'Lower_Princes', 'Maceio', 'Managua', 'Manaus', 'Marigot', 'Martinique', 'Matamoros', 'Mazatlan', 'Menominee', 'Merida', 'Metlakatla', 'Mexico_City', 'Miquelon', 'Moncton', 'Monterrey', 'Montevideo', 'Montserrat', 'Nassau', 'New_York', 'Nipigon', 'Nome', 'Noronha', 'North_Dakota' => ['Beulah', 'Center', 'New_Salem'], 'Ojinaga', 'Panama', 'Pangnirtung', 'Paramaribo', 'Phoenix', 'Port-au-Prince', 'Port_of_Spain', 'Porto_Velho', 'Puerto_Rico', 'Punta_Arenas', 'Rainy_River', 'Rankin_Inlet', 'Recife', 'Regina', 'Resolute', 'Rio_Branco', 'Santarem', 'Santiago', 'Santo_Domingo', 'Sao_Paulo', 'Scoresbysund', 'Sitka', 'St_Barthelemy', 'St_Johns', 'St_Kitts', 'St_Lucia', 'St_Thomas', 'St_Vincent', 'Swift_Current', 'Tegucigalpa', 'Thule', 'Thunder_Bay', 'Tijuana', 'Toronto', 'Tortola', 'Vancouver', 'Whitehorse', 'Winnipeg', 'Yakutat', 'Yellowknife'], 'Antarctica' => ['Casey', 'Davis', 'DumontDUrville', 'Macquarie', 'Mawson', 'McMurdo', 'Palmer', 'Rothera', 'Syowa', 'Troll', 'Vostok'], 'Arctic' => 'Longyearbyen', 'Asia' => ['Aden', 'Almaty', 'Amman', 'Anadyr', 'Aqtau', 'Aqtobe', 'Ashgabat', 'Atyrau', 'Baghdad', 'Bahrain', 'Baku', 'Bangkok', 'Barnaul', 'Beirut', 'Bishkek', 'Brunei', 'Chita', 'Choibalsan', 'Colombo', 'Damascus', 'Dhaka', 'Dili', 'Dubai', 'Dushanbe', 'Famagusta', 'Gaza', 'Hebron', 'Ho_Chi_Minh', 'Hong_Kong', 'Hovd', 'Irkutsk', 'Jakarta', 'Jayapura', 'Jerusalem', 'Kabul', 'Kamchatka', 'Karachi', 'Kathmandu', 'Khandyga', 'Kolkata', 'Krasnoyarsk', 'Kuala_Lumpur', 'Kuching', 'Kuwait', 'Macau', 'Magadan', 'Makassar', 'Manila', 'Muscat', 'Nicosia', 'Novokuznetsk', 'Novosibirsk', 'Omsk', 'Oral', 'Phnom_Penh', 'Pontianak', 'Pyongyang', 'Qatar', 'Qyzylorda', 'Riyadh', 'Sakhalin', 'Samarkand', 'Seoul', 'Shanghai', 'Singapore', 'Srednekolymsk', 'Taipei', 'Tashkent', 'Tbilisi', 'Tehran', 'Thimphu', 'Tokyo', 'Tomsk', 'Ulaanbaatar', 'Urumqi', 'Ust-Nera', 'Vientiane', 'Vladivostok', 'Yakutsk', 'Yangon', 'Yekaterinburg', 'Yerevan', 'Atlantic' => 'Azores', 'Bermuda', 'Canary', 'Cape_Verde', 'Faroe', 'Madeira', 'Reykjavik', 'South_Georgia', 'St_Helena', 'Stanley'], 'Australia' => ['Adelaide', 'Brisbane', 'Broken_Hill', 'Currie', 'Darwin', 'Eucla', 'Hobart', 'Lindeman', 'Lord_Howe', 'Melbourne', 'Perth', 'Sydney'], 'Europe' => ['Amsterdam', 'Andorra', 'Astrakhan', 'Athens', 'Belgrade', 'Berlin', 'Bratislava', 'Brussels', 'Bucharest', 'Budapest', 'Busingen', 'Chisinau', 'Copenhagen', 'Dublin', 'Gibraltar', 'Guernsey', 'Helsinki', 'Isle_of_Man', 'Istanbul', 'Jersey', 'Kaliningrad', 'Kiev', 'Kirov', 'Lisbon', 'Ljubljana', 'London', 'Luxembourg', 'Madrid', 'Malta', 'Mariehamn', 'Minsk', 'Monaco', 'Moscow', 'Oslo', 'Paris', 'Podgorica', 'Prague', 'Riga', 'Rome', 'Samara', 'San_Marino', 'Sarajevo', 'Saratov', 'Simferopol', 'Skopje', 'Sofia', 'Stockholm', 'Tallinn', 'Tirane', 'Ulyanovsk', 'Uzhgorod', 'Vaduz', 'Vatican', 'Vienna', 'Vilnius', 'Volgograd', 'Warsaw', 'Zagreb', 'Zaporozhye', 'Zurich'], 'Indian' => ['Antananarivo', 'Chagos', 'Christmas', 'Cocos', 'Comoro', 'Kerguelen', 'Mahe', 'Maldives', 'Mauritius', 'Mayotte', 'Reunion', 'Pacific' => 'Apia', 'Auckland', 'Bougainville', 'Chatham', 'Chuuk', 'Easter', 'Efate', 'Enderbury', 'Fakaofo', 'Fiji', 'Funafuti', 'Galapagos', 'Gambier', 'Guadalcanal', 'Guam', 'Honolulu', 'Kiritimati', 'Kosrae', 'Kwajalein', 'Majuro', 'Marquesas', 'Midway', 'Nauru', 'Niue', 'Norfolk', 'Noumea', 'Pago_Pago', 'Palau', 'Pitcairn', 'Pohnpei', 'Port_Moresby', 'Rarotonga', 'Saipan', 'Tahiti', 'Tarawa', 'Tongatapu', 'Wake', 'Wallis']];
    }

}
