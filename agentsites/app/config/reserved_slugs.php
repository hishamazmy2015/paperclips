<?php

/*
|--------------------------------------------------------------------------
| Reserved slugs (spec §7, §12). A tenant slug becomes {slug}.{base}, so anything
| the platform, mail, DNS or common tooling might use is reserved. Superset of
| the `reserved_slugs` table, which admins extend at runtime.
|--------------------------------------------------------------------------
*/

return [

    'min_length' => 3,
    'max_length' => 40,

    // lowercase, digits and hyphens; no leading/trailing hyphen
    'pattern' => '/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])?$/',

    'prefixes' => ['xn--', 'www-', 'admin-', 'api-'],

    'reserved' => [
        // platform hosts and infrastructure
        'www', 'app', 'admin', 'api', 'cdn', 'staging', 'static', 'assets', 'media', 'img', 'images',
        'status', 'health', 'internal', 'platform', 'system', 'sys', 'root', 'dev', 'test', 'demo',
        'preview', 'sandbox', 'beta', 'alpha', 'ops', 'monitor', 'metrics', 'grafana', 'prometheus',
        // mail and DNS
        'mail', 'smtp', 'imap', 'pop', 'pop3', 'mx', 'webmail', 'email', 'postmaster', 'hostmaster',
        'ns', 'ns1', 'ns2', 'dns', 'ftp', 'sftp', 'ssh', 'vpn', 'autoconfig', 'autodiscover',
        // product and account areas
        'login', 'logout', 'signin', 'signup', 'register', 'start', 'onboarding', 'account', 'accounts',
        'billing', 'pay', 'payments', 'invoice', 'invoices', 'checkout', 'pricing', 'plans', 'upgrade',
        'dashboard', 'settings', 'support', 'help', 'docs', 'blog', 'news', 'about', 'contact', 'legal',
        'terms', 'privacy', 'abuse', 'security', 'careers', 'jobs', 'press', 'partners', 'affiliates',
        // real-estate generic words we keep for platform landing pages
        'agent', 'agents', 'broker', 'brokers', 'brokerage', 'listings', 'properties', 'property',
        'realestate', 'real-estate', 'homes', 'villas', 'apartments', 'rent', 'sale', 'buy',
        // brand-ish
        'official', 'verified', 'team', 'teams', 'm', 'mobile', 'web', 'site', 'sites', 'home',
    ],

];
