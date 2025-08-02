<?php

$EM_CONF['cart_payone'] = [
    'title' => 'Cart - Payone',
    'description' => 'Shopping Cart(s) for TYPO3 - Payone Payment Provider',
    'category' => 'services',
    'author' => 'Daniel Gohlke',
    'author_email' => 'ext.cart@extco.de',
    'author_company' => 'extco.de UG (haftungsbeschränkt)',
    'state' => 'stable',
    'version' => '3.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-11.5.99',
            'cart' => '8.6.0',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
