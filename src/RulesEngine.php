<?php

namespace SimpleRulesEngine;

use InvalidArgumentException;

class RulesEngine
{
    /**
     * @param Rule|array<Rule|Rule[]> $rules
     */
    public static function run(mixed $subject, array|Rule $rules, bool $withHistory = false): ?Evaluation
    {
        $evaluation = self::runRecursively($subject, $rules, $withHistory);

        if ($evaluation instanceof Evaluation && $withHistory) {
            $history = $evaluation->getHistory();
            array_pop($history);
            $evaluation->setHistory($history);
        }

        return $evaluation;
    }

    /**
     * @param Rule|array<Rule|Rule[]> $rules
     */
    protected static function runRecursively(mixed $subject, array|Rule $rules, bool $withHistory = false): ?Evaluation
    {
        $evaluation = null;
        $previousEvaluation = null;
        /** @var Evaluation[] $history */
        $history = [];

        if ($rules instanceof Rule) {
            $rules = [$rules];
        }

        foreach ($rules as $rule) {
            if ($evaluation?->shouldStop()) {
                break;
            }

            /** @var Rule|array<Rule|Rule[]> $rule */
            if (is_array($rule)) {
                $evaluation = self::runRecursively($subject, $rule, $withHistory);

                if ($evaluation instanceof Evaluation && $withHistory) {
                    $history = array_merge($history, $evaluation->getHistory());
                    $evaluation->setHistory([]);
                }

                continue;
            }

            // @phpstan-ignore instanceof.alwaysTrue
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

            $history[] = $evaluation;
        }

        if ($evaluation instanceof Evaluation && $withHistory) {
            $evaluation->setHistory($history);
        }

        return $evaluation;
    }
}
