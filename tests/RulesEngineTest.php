<?php

namespace Tests\SimpleRulesEngine;

use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SimpleRulesEngine\Evaluation;
use SimpleRulesEngine\Rule;
use SimpleRulesEngine\RulesEngine;

/**
 * @see https://github.com/rogervila/python_simple_rules_engine/blob/main/tests/test_python_simple_rules_engine.py
 */
final class RulesEngineTest extends TestCase
{
    public function test_raises_value_error_if_list_contains_something_else_than_a_rule(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RulesEngine::run(uniqid('the subject '), [uniqid('not a rule ')]); // @phpstan-ignore-line
    }

    public function test_returns_evaluation_with_result(): void
    {
        $ruleClass = new class () extends Rule {
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                $evaluation = new Evaluation(result: null);
                $evaluation->setResult($subject === 'foo');

                return $evaluation;
            }
        };

        $rules = [new $ruleClass()];

        $evaluation = RulesEngine::run('foo', $rules);
        $this->assertInstanceOf(Evaluation::class, $evaluation);
        $this->assertTrue($evaluation->getResult());

        $evaluation = RulesEngine::run(uniqid('bar '), $rules);
        $this->assertInstanceOf(Evaluation::class, $evaluation);
        $this->assertFalse($evaluation->getResult());
    }

    public function test_returns_none_if_rules_list_is_empty(): void
    {
        $rules = [];
        $evaluation = RulesEngine::run(uniqid('the subject '), $rules);

        $this->assertNull($evaluation);
    }

    public function test_stops_when_defined(): void
    {
        $stopRuleClass = new class () extends Rule {
            public static int $count = 0;

            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                static::$count++;

                return new Evaluation(result: 'stopped', stop: true);
            }
        };

        $neverReachedRuleClass = new class () extends Rule {
            public static int $count = 0;

            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                static::$count++;

                return new Evaluation(result: 'never reached', stop: false);
            }
        };

        $rules = [$stopRule = new $stopRuleClass(), $neverReachedRule = new $neverReachedRuleClass()];
        $evaluation = RulesEngine::run(uniqid('the subject '), $rules);

        $this->assertInstanceOf(Evaluation::class, $evaluation);
        $this->assertInstanceOf($stopRuleClass::class, $evaluation->getRule());

        $this->assertEquals(1, $stopRule::$count);
        $this->assertEquals(0, $neverReachedRule::$count);
    }

    public function test_evaluation_extra_field(): void
    {
        $ruleAClass = new class () extends Rule {
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                return new Evaluation(result: uniqid(), extra: ['foo' => uniqid(), 'bar' => 'xyz']);
            }
        };

        $ruleBClass = new class () extends Rule {
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                $evaluation = new Evaluation(result: uniqid(), extra: $previousEvaluation?->getExtra() ?? []);
                $evaluation->addExtra(['foo' => 123]);

                return $evaluation;
            }
        };

        $rules = [new $ruleAClass(), new $ruleBClass()];
        $evaluation = RulesEngine::run(uniqid('the subject '), $rules);

        $this->assertInstanceOf(Evaluation::class, $evaluation);
        $this->assertEquals(['foo' => 123, 'bar' => 'xyz'], $evaluation->getExtra());
    }

    /**
     * @throws Exception
     */
    public function test_match_example_with_cards(): void
    {
        $amex = '375678956789765';
        $visa = '4345634566789888';
        $mastercard = '2228345634567898';
        $invalid = uniqid('invalid card ');

        $amexRuleClass = new class () extends Rule {
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                return new Evaluation(
                    result: $result = preg_match('/^3[47][0-9]{13}$/', strval($subject)) === 1 ? 'amex' : null,
                    stop: $result !== null,
                );
            }
        };

        $this->assertEquals('amex', $amexRuleClass->evaluate($amex)->getResult());

        $visaRuleClass = new class () extends Rule {
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                return new Evaluation(
                    result: $result = preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', strval($subject)) === 1 ? 'visa' : null,
                    stop: $result !== null,
                );
            }
        };

        $this->assertEquals('visa', $visaRuleClass->evaluate($visa)->getResult());

        $mastercardRuleClass = new class () extends Rule {
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                return new Evaluation(
                    result: $result = preg_match('/(5[1-5][0-9]{2}|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{2}|2720)[0-9]{12}/', strval($subject)) === 1 ? 'mastercard' : null,
                    stop: $result !== null,
                );
            }
        };

        $this->assertEquals('mastercard', $mastercardRuleClass->evaluate($mastercard)->getResult());

        $rules = [new $visaRuleClass(), new $amexRuleClass(), new $mastercardRuleClass()];

        for ($i = 0; $i < random_int(10, 20); $i++) {
            shuffle($rules);

            $evaluation = RulesEngine::run($amex, $rules);
            $this->assertInstanceOf(Evaluation::class, $evaluation);
            $this->assertEquals('amex', $evaluation->getResult());

            $evaluation = RulesEngine::run($visa, $rules);
            $this->assertInstanceOf(Evaluation::class, $evaluation);
            $this->assertEquals('visa', $evaluation->getResult());

            $evaluation = RulesEngine::run($mastercard, $rules);
            $this->assertInstanceOf(Evaluation::class, $evaluation);
            $this->assertEquals('mastercard', $evaluation->getResult());

            $evaluation = RulesEngine::run($invalid, $rules);
            $this->assertInstanceOf(Evaluation::class, $evaluation);
            $this->assertNull($evaluation->getResult());
        }
    }

    /**
     * @throws Exception
     */
    public function test_facts_example(): void
    {
        $animalClass = new class () {
            public string $eats = '';
            public string $lives = '';
            public string $color = '';
        };

        $frog = new $animalClass();
        $frog->eats = 'flies';
        $frog->lives = 'water';
        $frog->color = 'green';

        $bird = new $animalClass();
        $bird->eats = 'worms';
        $bird->lives = 'nest';
        $bird->color = 'black';

        $eatsRuleClass = new class () extends Rule {
            public const FACTS = ['flies' => 'frog', 'worms' => 'bird'];
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                $previousResult = $previousEvaluation?->getResult();
                $currentResult = self::FACTS[$subject?->eats];

                return new Evaluation(
                    result: $currentResult,
                    stop: $previousResult === $currentResult,
                );
            }
        };

        $this->assertEquals('frog', $eatsRuleClass->evaluate($frog)->getResult());
        $this->assertEquals('bird', $eatsRuleClass->evaluate($bird)->getResult());

        $livesRuleClass = new class () extends Rule {
            public const FACTS = ['water' => 'frog', 'nest' => 'bird'];
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                $previousResult = $previousEvaluation?->getResult();
                $currentResult = self::FACTS[$subject?->lives];

                return new Evaluation(
                    result: $currentResult,
                    stop: $previousResult === $currentResult,
                );
            }
        };

        $this->assertEquals('frog', $livesRuleClass->evaluate($frog)->getResult());
        $this->assertEquals('bird', $livesRuleClass->evaluate($bird)->getResult());

        $colorRuleClass = new class () extends Rule {
            public const FACTS = ['green' => 'frog', 'black' => 'bird'];
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                $previousResult = $previousEvaluation?->getResult();
                $currentResult = self::FACTS[$subject?->color];

                return new Evaluation(
                    result: $currentResult,
                    stop: $previousResult === $currentResult,
                );
            }
        };

        $this->assertEquals('frog', $colorRuleClass->evaluate($frog)->getResult());
        $this->assertEquals('bird', $colorRuleClass->evaluate($bird)->getResult());

        $rules = [new $eatsRuleClass(), new $livesRuleClass(), new $colorRuleClass()];

        for ($i = 0; $i < random_int(10, 20); $i++) {
            shuffle($rules);

            $evaluation = RulesEngine::run($frog, $rules);
            $this->assertInstanceOf(Evaluation::class, $evaluation);
            $this->assertEquals('frog', $evaluation->getResult());
            $this->assertEquals($rules[1]::class, $evaluation->getRule()::class); // @phpstan-ignore-line

            $evaluation = RulesEngine::run($bird, $rules);
            $this->assertInstanceOf(Evaluation::class, $evaluation);
            $this->assertEquals('bird', $evaluation->getResult());
            $this->assertEquals($rules[1]::class, $evaluation->getRule()::class); // @phpstan-ignore-line
        }
    }

    public function test_evaluation_with_history(): void
    {
        $ruleA = new class () extends Rule {
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                return new Evaluation(result: 'A', stop: false);
            }
        };

        $ruleB = new class () extends Rule {
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                return new Evaluation(result: 'B', stop: false);
            }
        };

        $ruleC = new class () extends Rule {
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                return new Evaluation(result: 'C', stop: false);
            }
        };

        $rules = [$ruleA, $ruleB, $ruleC];

        $evaluation = RulesEngine::run(
            subject: uniqid(),
            rules: $rules,
            withHistory: true
        );

        $this->assertCount(2, $evaluation->getHistory()); // @phpstan-ignore-line

        $this->assertEquals($ruleA::class, $evaluation->getHistory()[0]->getRule()::class); // @phpstan-ignore-line
        $this->assertEquals('A', $evaluation->getHistory()[0]->getResult()); // @phpstan-ignore-line

        $this->assertEquals($ruleB::class, $evaluation->getHistory()[1]->getRule()::class); // @phpstan-ignore-line
        $this->assertEquals('B', $evaluation->getHistory()[1]->getResult()); // @phpstan-ignore-line

        $this->assertEquals($ruleC::class, $evaluation->getRule()::class); // @phpstan-ignore-line
        $this->assertEquals('C', $evaluation->getResult()); // @phpstan-ignore-line
    }

    public function test_single_rule(): void
    {
        $ruleExample = new class () extends Rule {
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                return new Evaluation(result: 'foo', stop: false);
            }
        };

        $evaluation = RulesEngine::run(
            subject: uniqid(),
            rules: new $ruleExample()
        );

        $this->assertEquals($ruleExample::class, $evaluation->getRule()::class); // @phpstan-ignore-line
        $this->assertEquals('foo', $evaluation->getResult()); // @phpstan-ignore-line
    }

    public function test_multidimensional_rules(): void
    {
        $ARule = new class () extends Rule {
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                return new Evaluation(result: 'ARule', stop: false);
            }
        };

        $BRule = new class () extends Rule {
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                return new Evaluation(result: 'BRule', stop: false);
            }
        };

        $CRule = new class () extends Rule {
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                return new Evaluation(result: 'CRule', stop: false);
            }
        };

        $AARule = new class () extends Rule {
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                return new Evaluation(result: 'AARule', stop: false);
            }
        };

        $AAARule = new class () extends Rule {
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                return new Evaluation(result: 'AAARule', stop: false);
            }
        };

        $AABRule = new class () extends Rule {
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                return new Evaluation(result: 'AABRule', stop: true);
            }
        };

        $AACRule = new class () extends Rule {
            public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
            {
                return new Evaluation(result: 'AACRule', stop: false);
            }
        };

        $rules = [
            new $ARule(),
            new $BRule(),
            [
                new $AARule(),
                [
                    new $AAARule(),
                    new $AABRule(),
                    new $AACRule(),
                ]
            ],
            new $CRule()
        ];

        $evaluation = RulesEngine::run(
            subject: uniqid(),
            rules: $rules, // @phpstan-ignore-line
            withHistory: true
        );

        $this->assertCount(4, $evaluation->getHistory()); // @phpstan-ignore-line

        $this->assertEquals($ARule::class, $evaluation->getHistory()[0]->getRule()::class); // @phpstan-ignore-line
        $this->assertEquals('ARule', $evaluation->getHistory()[0]->getResult()); // @phpstan-ignore-line

        $this->assertEquals($BRule::class, $evaluation->getHistory()[1]->getRule()::class); // @phpstan-ignore-line
        $this->assertEquals('BRule', $evaluation->getHistory()[1]->getResult()); // @phpstan-ignore-line

        $this->assertEquals($AARule::class, $evaluation->getHistory()[2]->getRule()::class); // @phpstan-ignore-line
        $this->assertEquals('AARule', $evaluation->getHistory()[2]->getResult()); // @phpstan-ignore-line

        $this->assertEquals($AAARule::class, $evaluation->getHistory()[3]->getRule()::class); // @phpstan-ignore-line
        $this->assertEquals('AAARule', $evaluation->getHistory()[3]->getResult()); // @phpstan-ignore-line

        $this->assertEquals($AABRule::class, $evaluation->getRule()::class); // @phpstan-ignore-line
        $this->assertEquals('AABRule', $evaluation->getResult()); // @phpstan-ignore-line
    }
}
