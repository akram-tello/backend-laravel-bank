<?php

namespace App\Services;

use Exception;

class PrismaService
{
    private static $instance = null;
    private $prisma = null;

    private function __construct()
    {
        $this->initializePrisma();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializePrisma()
    {
        if ($this->prisma === null) {
            $nodeModulesPath = base_path('node_modules');
            $prismaClientPath = $nodeModulesPath . '/.prisma/client';

            if (!file_exists($prismaClientPath)) {
                throw new Exception('Prisma client not found. Please run `npx prisma generate` first.');
            }

            $this->prisma = new \PrismaClient();
        }
    }

    public function getPrisma()
    {
        return $this->prisma;
    }
}
