<?php

namespace SimpleRulesEngine;

class Evaluation
{
    /**
     * @param mixed[] $extra
     * @param Evaluation[] $history
     */
    public function __construct(
        protected mixed $result,
        protected ?Rule $rule = null,
        protected bool $stop = false,
        protected array $extra = [],
        protected array $history = [],
    )
    {
    }

    public function getRule(): ?Rule
    {
        return $this->rule;
    }

    public function setRule(?Rule $rule): self
    {
        $this->rule = $rule;
        return $this;
    }

    public function shouldStop(?bool $stop = null): bool
    {
        if (is_bool($stop)) {
            $this->stop = $stop;
        }

        return $this->stop;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    public function setResult(mixed $result): self
    {
        $this->result = $result;
        return $this;
    }

    /**
     * @return mixed[]
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    /**
     * @param mixed[] $extra
     */
    public function addExtra(array $extra): self
    {
        $this->extra = array_merge($this->extra, $extra);
        return $this;
    }

    /**
     * @return Evaluation[]
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    /**
     * @param Evaluation[] $history
     */
    public function setHistory(array $history): self
    {
        $this->history = $history;
        return $this;
    }
}
