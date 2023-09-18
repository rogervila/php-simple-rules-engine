<p align="center"><img width="200" src="https://rogervila.es/static/img/python_simple_rules_engine.png" alt="PHP Simple Rules Engine" /></p>

[![Build Status](https://github.com/rogervila/php-simple-rules-engine/workflows/build/badge.svg)](https://github.com/rogervila/php-simple-rules-engine/actions)
[![StyleCI](https://github.styleci.io/repos/684503105/shield?branch=main)](https://github.styleci.io/repos/684503105)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=rogervila_php-simple-rules-engine&metric=alert_status)](https://sonarcloud.io/dashboard?id=rogervila_php-simple-rules-engine)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=rogervila_php-simple-rules-engine&metric=coverage)](https://sonarcloud.io/dashboard?id=rogervila_php-simple-rules-engine)

[![Latest Stable Version](https://poser.pugx.org/rogervila/php-simple-rules-engine/v/stable)](https://packagist.org/packages/rogervila/php-simple-rules-engine)
[![Total Downloads](https://poser.pugx.org/rogervila/php-simple-rules-engine/downloads)](https://packagist.org/packages/rogervila/php-simple-rules-engine)
[![License](https://poser.pugx.org/rogervila/php-simple-rules-engine/license)](https://packagist.org/packages/rogervila/php-simple-rules-engine)

# PHP Simple Rules Engine

## About

Evaluate rules based on a subject.

## Usage

The package expects a subject and a list of rules.

Each rule must be a class that extends `\SimpleRulesEngine\Rule`.

The subject parameter can be any type of object (`mixed`)

### Basic usage

Rules return a `\SimpleRulesEngine\Evaluation` object that should contain a `result` property defined by the user.

Also, the user can define the value of the `stop` property to determine if the evaluation process should stop or continue.

In this example, the `stop` property value does not affect the evaluation process since we are evaluating only one rule.

```php
use SimpleRulesEngine\Rule;
use SimpleRulesEngine\Evaluation;
use SimpleRulesEngine\RulesEngine;

class FooRule extends Rule {
    public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
    {
        return new Evaluation(
            result: $subject === 'foo', // mixed. It should contain the evaluation result defined by the user.
            stop: false, // false by default. When set to true, the evaluation process is stopped.
        );
    }
}

$evaluation = RulesEngine::run(
    subject: 'foo',
    rules: [new FooRule()]
);

var_dump($evaluation->getResult()); // bool(true)
var_dump($evaluation->getRule()::class); // string(7) "FooRule"
```

### Advanced usage

When evaluating multiple rules you can retrieve the history of the rules evaluated for a specific evaluation process by passing the `withHistory` parameter as `true`.

The final `\SimpleRulesEngine\Evaluation` object will contain a `history` list with evaluations returned by the rules evaluated during the evaluation process.

Check `test_evaluation_with_history` method on `tests/RulesEngineTest.php` for a more detailed implementation.

```php
use SimpleRulesEngine\Rule;
use SimpleRulesEngine\Evaluation;
use SimpleRulesEngine\RulesEngine;

class RuleA extends Rule {
    // ...
}

class RuleB extends Rule {
    // ...
}

class RuleC extends Rule {
    // ...
}

$rules = [new RuleA(), new RuleB(), new RuleC()];

// Let's pretend that the final evaluation comes from RuleC()
$evaluation = RulesEngine::run(
    subject: 'C',
    rules: $rules,
    withHistory: true
);

var_dump(count($evaluation->getHistory())); // int(2)
var_dump($evaluation->getHistory()[0]->getRule()::class); // string(5) "RuleA"
var_dump($evaluation->getHistory()[1]->getRule()::class); // string(5) "RuleB"
```

### Recursive usage

You might pass rules and array of rules to recursively evaluate them.

> Note: A future version of this library might come with Openswoole support to parallelize the evaluation of each subset of rules.

```php
use SimpleRulesEngine\Evaluation;
use SimpleRulesEngine\RulesEngine;


$rules = [
    new ARule(), // 0
    new BRule(), // 1
    [
        new AARule(), // 2
        [
            new AAARule(), // 3
            new AABRule(), // 4
            new AACRule(), // 5
        ]
    ],
    new CRule() // 6
];

$evaluation = RulesEngine::run(
    subject: '...',
    rules: $rules
);
```

## Examples

The examples are very simple for demo purposes, but they show the basic features this package comes with.

There is a python rules engine called [durable rules](https://github.com/jruizgit/rules) that comes with some examples. We will recreate them with this package.

### Pattern matching

Find a credit card type based on its number.

Check `test_match_example_with_cards` method on `tests/RulesEngineTest.php` for a more detailed implementation.

```php
use SimpleRulesEngine\Rule;
use SimpleRulesEngine\Evaluation;
use SimpleRulesEngine\RulesEngine;

$amex = '375678956789765';
$visa = '4345634566789888';
$mastercard = '2228345634567898';
$invalid = uniqid('invalid card ');

class AmexRule extends Rule {
    public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
    {
        return new Evaluation(
            result: $result = preg_match('/^3[47][0-9]{13}$/', strval($subject)) === 1 ? 'amex' : null,
            stop: $result !== null,
        );
    }
};

class VisaRule extends Rule {
    public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
    {
        return new Evaluation(
            result: $result = preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', strval($subject)) === 1 ? 'visa' : null,
            stop: $result !== null,
        );
    }
};

class MastercardRule extends Rule {
    public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation
    {
        return new Evaluation(
            result: $result = preg_match('/(5[1-5][0-9]{2}|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{2}|2720)[0-9]{12}/', strval($subject)) === 1 ? 'mastercard' : null,
            stop: $result !== null,
        );
    }
};


$rules = [new VisaRule(), new AmexRule(), new MastercardRule()];

shuffle($rules); // Rules should apply always, the order does not matter

$evaluation = RulesEngine::run($amex, $rules);
var_dump($evaluation->getResult()); // string(4) "amex"
var_dump($evaluation->getRule()::class); // string(8) "AmexRule"

$evaluation = RulesEngine::run($visa, $rules);
var_dump($evaluation->getResult()); // string(4) "visa"
var_dump($evaluation->getRule()::class); // string(8) "VisaRule"

$evaluation = RulesEngine::run($mastercard, $rules);
var_dump($evaluation->getResult()); // string(10) "mastercard"
var_dump($evaluation->getRule()::class); // string(14) "MastercardRule"

$evaluation = RulesEngine::run($invalid, $rules);
var_dump($evaluation->getResult()); // NULL
var_dump($evaluation->getRule()::class); // Since we are using shuffle here, the rule applied can be any of the rules previously passed
```

### Set of facts

Find an animal based on facts.

In this case, we will compare the current rule result with the previous evaluation result. If they match, we stop the evaluation process.

Check `test_facts_example` method on `tests/RulesEngineTest.php` for a more detailed implementation.

```php
use SimpleRulesEngine\Rule;
use SimpleRulesEngine\Evaluation;
use SimpleRulesEngine\RulesEngine;

class Animal {
    public string $eats = '';
    public string $lives = '';
    public string $color = '';
};

$frog = new Animal();
$frog->eats = 'flies';
$frog->lives = 'water';
$frog->color = 'green';

$bird = new Animal();
$bird->eats = 'worms';
$bird->lives = 'nest';
$bird->color = 'black';

class EatsRule extends Rule {
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

class LivesRule extends Rule {
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

class ColorRule extends Rule {
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

$rules = [new EatsRule(), new LivesRule(), new ColorRule()];

shuffle($rules); // Rules should apply always, the order does not matter

$evaluation = RulesEngine::run($frog, $rules);
var_dump($evaluation->getResult()); // string(4) "frog"

$evaluation = RulesEngine::run($bird, $rules);
var_dump($evaluation->getResult()); // string(4) "bird"
```

## Author

Created by [Roger Vilà](https://rogervila.es)

## License

PHP Simple Rules Engine is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

<div>Icons made by <a href="https://www.flaticon.com/authors/gregor-cresnar" title="Gregor Cresnar">Gregor Cresnar</a> from <a href="https://www.flaticon.com/" title="Flaticon">www.flaticon.com</a></div>
