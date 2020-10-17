# yii2-zipkin

[![Build Status](https://travis-ci.org/luoxiaojun1992/yii2-zipkin.svg?branch=master)](https://travis-ci.org/luoxiaojun1992/yii2-zipkin)

## Description
Zipkin in Yii2

## Usage
1. Add zipkin to components
      ```
      'zipkin' => [
         'class' => TracerAlias::class,
         'serviceName' => 'basic',
         'endpointUrl' => 'http://192.168.99.100:9411/api/v2/spans',
         'sampleRate' => 2,
         'apiPrefix' => '/'
     ],
     ```
2. Config zipkin sample
    * For single controller
        add Lxj\Yii2\Zipkin\Filter to behaviors mathod.
        ```
        public function behaviors()
            {
                return [
                    'zipkin' => [
                        'class' => Filter::class,
                    ],
                ];
            }
        ```
    * For module
    ```
        class MyModule extends Lxj\Yii2\Zipkin\Module
    ```
Demo is [here](https://github.com/luoxiaojun1992/yii2-zipkin-demo).