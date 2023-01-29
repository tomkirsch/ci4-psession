<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit96945fb3975638d46022e46c4c3abe66
{
    public static $prefixLengthsPsr4 = array (
        'T' => 
        array (
            'Tomkirsch\\Psession\\' => 19,
        ),
        'P' => 
        array (
            'Psr\\Log\\' => 8,
        ),
        'L' => 
        array (
            'Laminas\\Escaper\\' => 16,
        ),
        'C' => 
        array (
            'CodeIgniter\\' => 12,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Tomkirsch\\Psession\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
        'Psr\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/log/Psr/Log',
        ),
        'Laminas\\Escaper\\' => 
        array (
            0 => __DIR__ . '/..' . '/laminas/laminas-escaper/src',
        ),
        'CodeIgniter\\' => 
        array (
            0 => __DIR__ . '/..' . '/codeigniter4/framework/system',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit96945fb3975638d46022e46c4c3abe66::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit96945fb3975638d46022e46c4c3abe66::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit96945fb3975638d46022e46c4c3abe66::$classMap;

        }, null, ClassLoader::class);
    }
}
