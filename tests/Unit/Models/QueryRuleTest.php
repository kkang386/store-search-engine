<?php

namespace Tests\Unit\Models;

use App\Models\QueryRule;
use Tests\TestCase;

class QueryRuleTest extends TestCase
{
    private function rule(string $pattern, string $type): QueryRule
    {
        return new QueryRule(['query_pattern' => $pattern, 'match_type' => $type]);
    }

    public function test_contains_matches_whole_word_not_substring(): void
    {
        $rule = $this->rule('bb', 'contains');

        // whole-word matches
        $this->assertTrue($rule->matchesQuery('airsoft bb'));
        $this->assertTrue($rule->matchesQuery('0.20g bb pellets'));
        $this->assertTrue($rule->matchesQuery('BB'));         // case-insensitive
        $this->assertTrue($rule->matchesQuery('airsoft-bb')); // hyphen is a boundary

        // substring inside another word must NOT match (the SKU-GBB004 regression)
        $this->assertFalse($rule->matchesQuery('sku-gbb004'));
        $this->assertFalse($rule->matchesQuery('gbb004'));
        $this->assertFalse($rule->matchesQuery('rabbit'));
    }

    public function test_contains_short_pattern_does_not_hijack_words(): void
    {
        $gun = $this->rule('gun', 'contains');
        $this->assertTrue($gun->matchesQuery('airsoft gun'));
        $this->assertFalse($gun->matchesQuery('shotgun'));

        $m4 = $this->rule('m4', 'contains');
        $this->assertTrue($m4->matchesQuery('m4 carbine'));
        $this->assertFalse($m4->matchesQuery('m4a1'));
    }

    public function test_contains_matches_multi_word_phrase(): void
    {
        $rule = $this->rule('airsoft gun', 'contains');
        $this->assertTrue($rule->matchesQuery('buy airsoft gun now'));
        $this->assertFalse($rule->matchesQuery('airsoft rifle'));
    }

    public function test_contains_treats_regex_specials_literally(): void
    {
        // pattern with regex-special / trailing-punctuation must not error or act as a regex
        $rule = $this->rule('c++', 'contains');
        $this->assertTrue($rule->matchesQuery('learn c++ today'));
        $this->assertFalse($rule->matchesQuery('learn cpp today'));
    }

    public function test_exact_and_starts_with_unchanged(): void
    {
        $this->assertTrue($this->rule('bb', 'exact')->matchesQuery('BB'));
        $this->assertFalse($this->rule('bb', 'exact')->matchesQuery('airsoft bb'));

        $this->assertTrue($this->rule('lancer', 'starts_with')->matchesQuery('lancer tactical'));
        $this->assertFalse($this->rule('lancer', 'starts_with')->matchesQuery('buy lancer'));
    }
}
