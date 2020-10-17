# yii2-zipkin

[![Build Status](https://travis-ci.org/luoxiaojun1992/yii2-zipkin.svg?branch=master)](https://travis-ci.org/luoxiaojun1992/yii2-zipkin)

## Description
Zipkin in Yii2

## Usage
1. Add zipkin to components
      ```
      'zipkin' => [
         'class' => TracerAlias::class,
         'serviceName' => 'yii2',
         'endpointUrl' => 'http://127.0.0.1:9411/api/v2/spans',
         'sampleRate' => 1,
         'apiPrefix' => '/'
     ],
     ```
2. Add zipkin sampler
    * For single controller
        ```
        //Add Lxj\Yii2\Zipkin\Filter to behaviors mathod.
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
        //Define your module extended 'Lxj\Yii2\Zipkin\Module'
        class MyModule extends Lxj\Yii2\Zipkin\Module
        ```
3. Demo is [here](https://github.com/luoxiaojun1992/yii2-zipkin-demo).