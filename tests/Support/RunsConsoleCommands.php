<?php

namespace HalaeiTests\Support;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

trait RunsConsoleCommands
{
    protected function runCommand(Command $command, array $arguments = [], array $options = []): BufferedOutput
    {
        $command->setLaravel($this->app);

        $input = new ArrayInput(array_merge($arguments, $options));
        $input->bind($command->getDefinition());

        $output = new BufferedOutput;
        $command->run($input, new \Illuminate\Console\OutputStyle($input, $output));

        return $output;
    }

    protected function makeBoundCommand(Command $command, array $arguments = [], array $options = []): Command
    {
        $command->setLaravel($this->app);

        $input = new ArrayInput(array_merge($arguments, $options));
        $input->bind($command->getDefinition());
        $output = new BufferedOutput;
        $command->setInput($input);
        $command->setOutput(new \Illuminate\Console\OutputStyle($input, $output));

        return $command;
    }
}
