<?php

namespace SimpleRulesEngine;

abstract class Rule
{
    public abstract function evaluate(mixed $subject, ?Evaluation $previousEvaluation = null): Evaluation;
}
