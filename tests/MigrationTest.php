<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;

class MigrationTest extends TestCase {
    public static function setUpBeforeClass(): void {
        self::resetDatabase();
    }

    public static function tearDownAfterClass(): void {
        self::resetDatabase();
        $projectDirectoryPath = dirname(__FILE__, 2);
        exec("rm {$projectDirectoryPath}/*migration*.csv");
    }

    private static function resetDatabase(): void {
        $parentDirectoryPath = dirname(__FILE__);
        exec("sudo mysql nextcloud < {$parentDirectoryPath}/test_database_dump.sql");
    }

    public function testMigration() {
        $projectDirectoryPath = dirname(__FILE__, 2);
        exec("php {$projectDirectoryPath}/app.php migrate", $output, $resultCode);
        $this->assertSame(0, $resultCode);
    }
}
