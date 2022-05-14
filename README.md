CompactHtml
===============
Version 1.0.0

Introduction
------------
CompactHtml is a module for Laminas Framework (ZF3) that minimizes, compresses and caches pages HTML, 
improving the performance of your web application

Installation
------------

- Clone this module into your `module/` directory and rename to `CompactHtml` 
- Enable `CompactHtml` in `config/modules.config.php`
- Add the following line in `composer.json` for autoload
```php
{
  "autoload": {
    "psr-4": {
      "CompactHtml\\": "module/CompactHtml/src/",
    }
  }
}
```
- Run `composer dump-autoload` to autoload the new module

Module Configuration
----------------
Create config/autoload/compact-html.global.php with the content:

```php
<?php
return [
    'compact-html' => [
        // Enable/disable minimizes and compresses HTML functionality
        'enabled' => true,

        // Enable/disable cache functionality
        'cache' => true,
        'cache-options' => [
            // type support: storage | redis; default: storage
            'type' => 'storage',
            // configuração do storage
            'cache-storage-options' => [
                // Diretorio para salvar os caches
                'cache-dir' => './data/cache',
                // tempo de validade do cache em segundos
                'ttl' => 120,
                'namespace' => '-pg-',
            ],
            // configuração do redis
            'cache-redis-options' => [
                'host' => 'localhost',
                'port' => 6379,
            ],

            'list-route-controller' => [
                // allow support: all | none; default: all
                'allow' => 'none',
                // if "allow" is 'none', "except" is the allowed cache list
                // if "allow" is 'all', "except" is the blocked cache list
                'except' => [
                    'site',
                ],
            ],
        ],
    ],
];
```