<?php

namespace studioespresso\standardsite\tests\helpers;

use PHPUnit\Framework\TestCase;
use studioespresso\standardsite\helpers\Tid;

class TidTest extends TestCase
{
    public function test_tid_is_13_characters(): void
    {
        $tid = Tid::generate();
        $this->assertSame(13, strlen($tid));
    }

    public function test_tid_contains_only_valid_base32_characters(): void
    {
        $tid = Tid::generate();
        $this->assertMatchesRegularExpression('/^[234567a-z]{13}$/', $tid);
    }

    public function test_sequential_tids_are_unique(): void
    {
        $tid1 = Tid::generate();
        $tid2 = Tid::generate();
        $this->assertNotSame($tid1, $tid2);
    }

    public function test_tids_are_lexicographically_sortable(): void
    {
        $tid1 = Tid::generate();
        usleep(1000); // 1ms gap to ensure different timestamp
        $tid2 = Tid::generate();

        $this->assertLessThan(0, strcmp($tid1, $tid2), 'Later TID should sort after earlier TID');
    }

    public function test_many_tids_are_all_valid(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $tid = Tid::generate();
            $this->assertSame(13, strlen($tid));
            $this->assertMatchesRegularExpression('/^[234567a-z]{13}$/', $tid);
        }
    }
}
