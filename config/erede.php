<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credenciais
    |--------------------------------------------------------------------------
    |
    | No OAuth 2.0 da Rede o PV é o clientId e a chave de integração é o
    | clientSecret. O ambiente aceita "sandbox" ou "production".
    |
    */

    'mode' => env('EREDE_MODE', 'sandbox'),

    'pv' => env('EREDE_PV'),

    'token' => env('EREDE_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    |
    | Proxy: defina EREDE_PROXY para aplicar o mesmo proxy a http e https, ou
    | use as chaves específicas quando precisar separá-los. EREDE_PROXY_NO
    | aceita uma lista separada por vírgula de hosts que devem ignorar o proxy.
    |
    | Formato aceito: [protocolo://][usuario:senha@]host:porta
    | Ex.: http://proxy.empresa.com.br:8080
    |
    | Não há retry automático: a criação de transação é um POST não idempotente
    | e uma retentativa cega pode gerar cobrança duplicada.
    |
    */

    'http' => [

        'proxy' => [
            'http' => env('EREDE_PROXY_HTTP', env('EREDE_PROXY')),
            'https' => env('EREDE_PROXY_HTTPS', env('EREDE_PROXY')),
            'no' => env('EREDE_PROXY_NO'),
        ],

        // Segundos de espera pela resposta completa da Rede.
        'timeout' => (int) env('EREDE_TIMEOUT', 60),

        // Segundos de espera para estabelecer a conexão TCP.
        'connect_timeout' => (int) env('EREDE_CONNECT_TIMEOUT', 10),

        // Timeout específico da chamada de autenticação OAuth.
        'auth_timeout' => (int) env('EREDE_AUTH_TIMEOUT', 30),

        // Verificação do certificado TLS. Mantenha true fora de depuração local.
        'verify' => env('EREDE_VERIFY_SSL', true),

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache do access_token
    |--------------------------------------------------------------------------
    |
    | O access_token é reaproveitado até expirar. Deixe "store" nulo para usar
    | o cache padrão da aplicação. Em ambiente com múltiplos workers prefira um
    | store compartilhado (redis, memcached) em vez de "array" ou "file".
    |
    */

    'cache' => [

        'store' => env('EREDE_CACHE_STORE'),

        'prefix' => env('EREDE_CACHE_PREFIX', 'erede.access_token'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Log
    |--------------------------------------------------------------------------
    |
    | O pacote registra o canal abaixo em logging.channels automaticamente, a
    | menos que a aplicação já tenha definido um canal com o mesmo nome — nesse
    | caso o da aplicação prevalece e "channel_config" é ignorado.
    |
    | Para mandar os logs do eRede para o canal padrão da aplicação, defina
    | EREDE_LOG_CHANNEL=null. Para silenciá-los, EREDE_LOG_ENABLED=false.
    |
    */

    'logging' => [

        'enabled' => env('EREDE_LOG_ENABLED', true),

        'channel' => env('EREDE_LOG_CHANNEL', 'erede'),

        'channel_config' => [
            'driver' => 'daily',
            'path' => storage_path('logs/erede.log'),
            'level' => env('EREDE_LOG_LEVEL', 'debug'),
            'days' => (int) env('EREDE_LOG_DAYS', 14),
            'replace_placeholders' => true,
        ],

    ],

];
