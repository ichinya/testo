<?php

class IndependentDynamicConstraints
{
    public function repeatedMessageAndPattern(): void
    {
        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('old');
        $this->expectExceptionMessageMatches('~two~');
        $this->expectExceptionMessage($this->message());
    }

    public function dynamicMessageAndPattern(): void
    {
        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage($this->message());
        $this->expectExceptionMessageMatches('~two~');
    }
}
