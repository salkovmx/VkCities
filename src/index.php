<?php

new VkCities();

class VkCities {
    const SAVE_PATH = '../dist';
    const ACCESS_TOKEN = '';
    const VERSION = '5.103';
    const DEFAULT_LANG = 0;
    const LIMIT = 1000;
    const LANGS = [
        0 => 'ru',
        3 => 'en',
    ];

    static $queries_count = 0;

    function __construct() {
        set_time_limit(0);

        echo 'Languages: ' . implode(', ', self::LANGS) . "\n";

        $this->run();

        echo 'Queries count: ' . self::$queries_count . "\n";

        echo "Done.";
    }

    function run() {
        $countries = self::getCountries();
        $countries_count = count($countries);
        $cities_count = 0;

        $i = 0;

        echo "Countries: {$countries_count}\n";

        self::saveCountries(array_values($countries));

        foreach ($countries as $country) {
            $i++;

            echo "({$i}/{$countries_count}) ";
            echo "Loading country #{$country['id']}'" . $country['title_' . self::LANGS[self::DEFAULT_LANG]] . "'...\n";

            $cities = self::getCities($country['id']);
            $cities_count += count($cities);

            self::saveCountry($country['id'], $country + [
                    'cities' => $cities,
                ]);

            echo "Saved " . count($cities) . " cities\n";
        }

        echo "\nCountries: {$countries_count}. Cities: {$cities_count}\n";

        return $countries;
    }

    static function getCountries() {
        $countries = [];

        foreach (self::LANGS as $lang_id => $lang_name) {
            $vk_countries = self::vk_method('database.getCountries', [
                'lang' => $lang_id,
            ]);

            foreach ($vk_countries as $country) {
                $country['id'] = intval($country['id']);
                $countries[$country['id']]['id'] = $country['id'];
                $countries[$country['id']]['title_' . $lang_name] = $country['title'];
            }
        }

        return $countries;
    }

    static function getCities($country_id) {
        $cities = [];

        $offset = 0;
        while (true) {
            $cities_append = self::getCitiesPart($country_id, $offset);

            if (!count($cities_append))
                break;

            $cities = array_merge($cities, $cities_append);

            $offset += self::LIMIT;

            if ($offset % (self::LIMIT * 10) == 0) {
                echo "Loaded cities: {$offset}...\n";
            }
        }

        return $cities;
    }

    static function getCitiesPart($country_id, $offset = 0) {
        $cities = self::vk_method('database.getCities', [
            'country_id' => $country_id,
            'offset'     => $offset,
        ]);

        $city_ids = [];
        foreach ($cities as $city) {
            $city_ids[] = $city['id'];
        }
        $city_ids = implode(',', $city_ids);

        $result = [];
        foreach (self::LANGS as $lang_id => $lang_name) {
            $cities_lang = self::vk_method('database.getCitiesById', [
                'city_ids' => $city_ids,
                'lang'     => $lang_id,
            ]);

            foreach ($cities_lang as $city) {
                $result[$city['id']]['id'] = $city['id'];
                $result[$city['id']]['title_' . $lang_name] = $city['title'];
            }
        }

        return $result;
    }

    static function saveCountries($countries) {
        return file_put_contents(
            self::SAVE_PATH . '/countries.json',
            json_encode($countries, JSON_UNESCAPED_UNICODE)
        );
    }

    static function saveCountry($id, $country) {
        return file_put_contents(
            self::SAVE_PATH . "/countries/{$id}.json",
            json_encode($country, JSON_UNESCAPED_UNICODE)
        );
    }

    static function vk_method($method, $params = []) {
        $common_params = [
            'v'            => self::VERSION,
            'lang'         => self::DEFAULT_LANG,
            'access_token' => self::ACCESS_TOKEN,
            'count'        => self::LIMIT,
            'need_all'     => 1,
        ];

        $params = array_merge($common_params, $params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.vk.com/method/' . $method);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = json_decode(curl_exec($ch), 1);

        curl_close($ch);

        self::$queries_count++;

        if (!isset($response['response'])) {
            exit(json_encode($response));
        }

        $response = $response['response'];

        return $response['items'] ?? $response;
    }
}