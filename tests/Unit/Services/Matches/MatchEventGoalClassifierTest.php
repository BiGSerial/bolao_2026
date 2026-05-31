<?php

namespace Tests\Unit\Services\Matches;

use App\Services\Matches\MatchEventGoalClassifier;
use Tests\TestCase;

class MatchEventGoalClassifierTest extends TestCase
{
    public function test_detects_notifiable_goal(): void
    {
        $classifier = new MatchEventGoalClassifier();

        $this->assertTrue($classifier->isNotifiableGoal('Goal', 'Normal Goal'));
    }

    public function test_detects_disallowed_goal(): void
    {
        $classifier = new MatchEventGoalClassifier();

        $this->assertTrue($classifier->isDisallowedGoal('Goal Disallowed'));
        $this->assertFalse($classifier->isNotifiableGoal('Goal', 'Goal Disallowed'));
    }

    public function test_detects_portuguese_disallowed_goal_words(): void
    {
        $classifier = new MatchEventGoalClassifier();

        $this->assertTrue($classifier->isDisallowedGoal('Gol anulado por impedimento'));
    }
}
