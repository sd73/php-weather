<?php

/**
 * @name парсер погоды от openweathermap.org
 * @author Sergey Demidov <admin@sd73.ru>
 */
$options = getopt("o:");
$id = 479123;
$appid = 'c069831f0d5cb76e589bfda84510e079';
$lang = 'ru';
$mode = 'xml';
$units = 'metric';
$cache_path = dirname(__FILE__) . '/weather.xml'; // путь кеша погоды
$source = "http://api.openweathermap.org/data/2.5/weather?id=$id&appid=$appid&units=$units&lang=$lang&mode=$mode"; // адрес данных о погоде

$data = new Weather();
$data->setCachePath($cache_path);

if (count($options) == 0) {
    echo $data->refresh($source, $cache_path);
} else {
    $weaterarr = $data->getWeather();
    echo $data->showWeather($options['o']);
}

exit();

class Weather
{

    private $data;

    private $path;

    private $weather;

    function __construct()
    {
        $this->data = NULL;
        $this->path = '';
        $this->weather = array();
    }

    public function setCachePath($cache_path)
    {
        $this->path = $cache_path;
    }

    public function refresh($source)
    {
        file_put_contents($this->path, file_get_contents($source, true));
        return 'обновлено';
    }

    public function getWeather()
    {
        /**
         *
         * @todo заменить статическую переменную $units на динамическую
         */
        $units = 'metric';
        $xml = simplexml_load_file($this->path); // раскладываем xml на массив
        $this->weather['id'] = $xml->city['id']; // id-города
        $this->weather['name'] = $xml->city['name']; // название города
        $this->weather['lon'] = $xml->city->coord['lon']; // долгота
        $this->weather['lat'] = $xml->city->coord['lat']; // широта
        $this->weather['country'] = $xml->city->country; // страна
        $this->weather['sunrise'] = date('d-m-Y H:i:s', strtotime($xml->city->sun['rise'] . " UTC")); // время восхода
        $this->weather['sunset'] = date('d-m-Y H:i:s', strtotime($xml->city->sun['set'] . " UTC")); // время заката
        $this->weather['temperature'] = $xml->temperature['value'] . (($xml->temperature['unit'] == 'metric') ? 'ºC' : 'F'); // температура сейчас
        $this->weather['temperaturemin'] = $xml->temperature['min'] . (($xml->temperature['unit'] == 'metric') ? 'ºC' : 'F'); // температура минимум
        $this->weather['temperaturemax'] = $xml->temperature['max'] . (($xml->temperature['unit'] == 'metric') ? 'ºC' : 'F'); // температура максимум
        $this->weather['humidity'] = $xml->humidity['value'] . $xml->humidity['unit']; // влажность
        $this->weather['pressuremm'] = (intval($xml->pressure['value'] * 0.75006375541921) . ' мм рт.ст.'); // давление в мм рт.ст.
        $this->weather['pressurePa'] = $xml->pressure['value'] . ' гПа'; // давление в Паскалях
        /**
         *
         * @todo парсинг wind->speed['name'] название ветра
         * @todo парсинг wind->gusts
         */
        $this->weather['windshort'] = self::langShortWind($xml->wind->direction['code']); // ветер коротко
        $this->weather['windlong'] = self::langFullWind($xml->wind->direction['code']); // ветер полное наименование
        $this->weather['windspeed'] = $xml->wind->speed['value'] . ' ' . 'м/с'; // скорость ветера
        $this->weather['windrumb'] = $xml->wind->direction['value'] . 'º'; // направление ветра в румбах
        $this->weather['windrumbshort'] = self::langShortWind(self::parsingWindRumb($xml->wind->direction['value'])); // направление ветра по румбам
        $this->weather['windrumbfull'] = self::langFullWind(self::parsingWindRumb($xml->wind->direction['value'])); // направление ветра по румбам
        /**
         *
         * @todo парсинг clouds['value']
         */
        $this->weather['clouds'] = $xml->clouds['name']; // облачность
        $this->weather['visibility'] = ($xml->visibility['value'] > 999) ? ($xml->visibility['value'] / 1000) . ' км' : $xml->visibility['value'] . ' м'; // видимость
        $this->weather['precipitation'] = self::parsingPrecipitation($xml->precipitation['mode'], $xml); // осадки
        /**
         *
         * @todo парсинг блока <weather number="804" value="пасмурно" icon="04n"></weather>
         */
        $this->weather['updatetime'] = date('d-m-Y H:i:s', strtotime($xml->lastupdate['value'] . " UTC")); // когда обновлена погода
    }

    private function parsingPrecipitation($param, $xml)
    {
        if ($param == 'no') {
            return 'без осадков';
        } elseif ($param == 'rain') {
            return $xml->precipitation['value'] . 'мм, Дождь';
        } elseif ($param == 'snow') {
            return $xml->precipitation['value'] . 'мм, Снег';
        }
    }

    private function langShortWind($value)
    {
        return array_search($value, array(
            'С' => 'N',
            'ССВ' => 'NNE',
            'СВ' => 'NE',
            'ВСВ' => 'ENE',
            'В' => 'E',
            'ВЮВ' => 'ESE',
            'ЮВ' => 'SE',
            'ЮЮВ' => 'SSE',
            'Ю' => 'S',
            'ЮЮЗ' => 'SSW',
            'ЮЗ' => 'SW',
            'ЗЮЗ' => 'WSW',
            'З' => 'W',
            'ЗСЗ' => 'WNW',
            'СЗ' => 'NW',
            'ССЗ' => 'NNW'
        ));
    }

    private function langFullWind($value)
    {
        return array_search($value, array(
            'Северный' => 'N',
            'Северный, северо-восточный' => 'NNE',
            'Северо-восточный' => 'NE',
            'Восточный, северо-восточный' => 'ENE',
            'Восточный' => 'E',
            'Восточный, юго-восточный' => 'ESE',
            'Юго-восточный' => 'SE',
            'Южный, юго-восточный' => 'SSE',
            'Южный' => 'S',
            'Южный, юго-западный' => 'SSW',
            'Юго-западный' => 'SW',
            'Западный, юго-западный' => 'WSW',
            'Западный' => 'W',
            'Западный, северо-западный' => 'WNW',
            'Северо-западный' => 'NW',
            'Северный, северо-западный' => 'NNW'
        ));
    }

    private function parsingWindRumb($value)
    {
        if ($value > 348.75 or $value <= 11.25)
            return 'N';
        if ($value > 11.25 and $value <= 33.75)
            return 'NNE';
        if ($value > 33.75 and $value <= 56.25)
            return 'NE';
        if ($value > 56.25 and $value <= 78.75)
            return 'ENE';
        if ($value > 78.75 and $value <= 101.25)
            return 'E';
        if ($value > 101.25 and $value <= 123.75)
            return 'ESE';
        if ($value > 123.75 and $value <= 146.25)
            return 'SE';
        if ($value > 146.25 and $value <= 168.75)
            return 'SSE';
        if ($value > 168.75 and $value <= 191.25)
            return 'S';
        if ($value > 191.25 and $value <= 213.75)
            return 'SSW';
        if ($value > 213.75 and $value <= 236.25)
            return 'SW';
        if ($value > 236.25 and $value <= 258.75)
            return 'WSW';
        if ($value > 258.75 and $value <= 281.25)
            return 'W';
        if ($value > 281.25 and $value <= 303.75)
            return 'WNW';
        if ($value > 303.75 and $value <= 326.25)
            return 'NW';
        if ($value > 326.25 and $value <= 348.75)
            return 'NNW';
    }

    public function showWeather($id)
    {
        if (array_key_exists($id, $this->weather)) {
            return $this->weather[$id];
        } else {
            return "Ключ '$id' для вывода не найден.\nДоступные ключи для опции -o: " . implode(array_keys($this->weather), ', ') . ".\nДамп погоды: " . implode(', ', $this->weather);
        }
    }
}

?>
