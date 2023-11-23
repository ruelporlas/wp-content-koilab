<?php return array(
    'root' => array(
        'name' => 'easydigitaldownloads/edd-recurring',
        'pretty_version' => 'dev-master',
        'version' => 'dev-master',
        'reference' => '9a1ddc2933f09104792734ba8ef0d9e508249ede',
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'easydigitaldownloads/edd-addon-tools' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => 'f3e56fe5e2bc231a38679bb71dcb08ca30657db6',
            'type' => 'library',
            'install_path' => __DIR__ . '/../easydigitaldownloads/edd-addon-tools',
            'aliases' => array(
                0 => '9999999-dev',
            ),
            'dev_requirement' => false,
        ),
        'easydigitaldownloads/edd-recurring' => array(
            'pretty_version' => 'dev-master',
            'version' => 'dev-master',
            'reference' => '9a1ddc2933f09104792734ba8ef0d9e508249ede',
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
