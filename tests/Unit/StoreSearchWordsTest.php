<?php

namespace Tests\Unit;

use App\Models\Store;
use PHPUnit\Framework\TestCase;

/**
 * The All Orders store search splits what the user typed into words; every
 * word must then match the store's DFID or name.
 */
class StoreSearchWordsTest extends TestCase
{
    public function test_splits_on_spaces(): void
    {
        $this->assertSame(['Sneaker', 'Lab', 'BD'], Store::searchWords('Sneaker Lab BD'));
    }

    public function test_a_full_store_label_splits_on_the_middle_dot(): void
    {
        $this->assertSame(['DFSNK001', 'Sneaker', 'Lab', 'BD'], Store::searchWords('DFSNK001 · Sneaker Lab BD'));
        $this->assertSame(['DFSNK001', 'Sneaker'], Store::searchWords('DFSNK001·Sneaker'));
    }

    public function test_trims_and_collapses_extra_whitespace(): void
    {
        $this->assertSame(['dfsnk', 'lab'], Store::searchWords("  dfsnk \t  lab  "));
    }

    public function test_a_single_partial_id_stays_one_word(): void
    {
        $this->assertSame(['dfsnk'], Store::searchWords('dfsnk'));
    }

    public function test_blank_input_means_no_store_filter(): void
    {
        $this->assertSame([], Store::searchWords(null));
        $this->assertSame([], Store::searchWords(''));
        $this->assertSame([], Store::searchWords('   '));
        $this->assertSame([], Store::searchWords(' · '));
    }
}
