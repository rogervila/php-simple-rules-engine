<?php

namespace SimpleRulesEngine;

use InvalidArgumentException;

class RulesEngine
{
    /**
     * @param Rule[] $rules
     */
    public static function run(mixed $subject, array $rules, bool $withHistory = false): ?Evaluation
    {
        $evaluation = null;
        $previousEvaluation = null;
        /** @var Evaluation[] $history */
        $history = [];

        foreach ($rules as $i => $rule) {
            if (!$rule instanceof Rule) {
                throw new InvalidArgumentException(sprintf(
                    '"%s" should be an instance of %s',
                    get_debug_type($rule),
                    Rule::class
                ));
            }

            if ($evaluation instanceof Evaluation) {
                $previousEvaluation = clone $evaluation;
            }

            $evaluation = $rule->evaluate($subject, $previousEvaluation);
            $evaluation->setRule($rule);

            if ($i > 0 && $withHistory && $previousEvaluation instanceof Evaluation) {
                $history[] = $previousEvaluation;
            }

            if ($evaluation->shouldStop()) {
                break;
            }
        }

        $evaluation?->setHistory($history);

        return $evaluation;
    }
}
