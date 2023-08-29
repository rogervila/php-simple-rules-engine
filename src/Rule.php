<?php

namespace SimpleRulesEngine;

abstract class Rule
{
    abstract public function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation;
}
