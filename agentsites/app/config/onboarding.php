<?php

/*
|--------------------------------------------------------------------------
| Onboarding (spec §13): the area chips of S4, the seeded brokerage list S2
| autocompletes from (free text stays allowed), and the S5 checklist.
|--------------------------------------------------------------------------
*/

return [

    'areas' => [
        'Downtown', 'Marina', 'Palm Jumeirah', 'JVC', 'Business Bay', 'Dubai Hills',
        'Yas Island', 'Saadiyat', 'Al Reem', 'Khalifa City',
    ],

    'brokerages' => [
        'Allsopp & Allsopp', 'AX Capital', 'Betterhomes', 'Chestertons MENA', 'Coldwell Banker UAE',
        'Crompton Partners', 'Dacha Real Estate', 'Driven Properties', 'Elite Property Brokerage',
        'Engel & Völkers Dubai', 'Espace Real Estate', 'fäm Properties', 'Gulf Sotheby\'s International Realty',
        'haus & haus', 'Huspy', 'Kaizen AMS', 'Knight Frank UAE', 'LuxuryProperty.com', 'McCone Properties',
        'Metropolitan Premium Properties', 'Nomad Homes', 'Provident Real Estate', 'PSI Real Estate',
        'RE/MAX UAE', 'Savills Middle East', 'Treo Homes', 'Unique Properties', 'White & Co Real Estate',
    ],

    // reminders after an abandoned onboarding (spec §13): minutes since the last change
    'reminders' => [
        '1h' => 60,
        '24h' => 1440,
    ],

    'checklist' => ['listings', 'domain', 'testimonials', 'instagram', 'logo'],

];
