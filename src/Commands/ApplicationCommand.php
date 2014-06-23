<?php
namespace Iono\Console\Commands;

use ReflectionClass;
use Iono\Console\Command;
use Iono\Console\Tokenizer;
use Iono\Console\Container;
use Iono\Console\Exception\ClassNotFoundException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ApplicationCommand
 * @package Iono\Console\Commands
 * @author yuuki.takezawa<yuuki.takezawa@comnect.jp.net>
 */
class ApplicationCommand extends Command
{

    /** @var string  */
    protected $command = "console:action";

    /** @var string */
    protected $description = "application scripts";

    /** @var Tokenizer */
    protected $tokenizer;

    /** @var Container */
    protected $container;

    /**
     * @param Tokenizer $tokenizer
     * @param Container $container
     */
    public function __construct(Tokenizer $tokenizer, Container $container)
    {
        parent::__construct();
        $this->tokenizer = $tokenizer;
        $this->container = $container;
    }

    /**
     * @return mixed|void
     */
    public function arguments()
    {
        $this->addArgument('action', InputArgument::REQUIRED, 'specify your script name');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return mixed|void
     * @throws \Iono\Console\Exception\ClassNotFoundException
     */
    public function action(InputInterface $input, OutputInterface $output)
    {
        //
        $query = [];
        //
        $parsed = parse_url($input->getArgument('action'));
        // search application
        $application = $this->tokenizer->getApplication();
        // @todo refactor
        //
        if(isset($parsed['path'])) {
            //
            $alias = $application->getAliases()[$application['prefix'] . $parsed['path']];
            if(!$alias) {
                throw new ClassNotFoundException("Not Found [{$parsed['path']}] command");
            }
            // query parse
            if(isset($parsed['query'])) {
                parse_str($parsed['query'], $query);
            }
            $reflectionClass = new ReflectionClass($alias);
            $constructor = $reflectionClass->getConstructor();

            if (is_null($constructor))
            {
                $class = $reflectionClass->newInstance();
            }
            $dependencies = $constructor->getParameters();


            if(count($dependencies)) {

                foreach ($dependencies as $parameter) {
                    $params[] = $this->container->make($parameter->getClass()->name);
                }
                $class = $reflectionClass->newInstanceArgs($params);
            }

            $traits = $reflectionClass->getTraits();
            if(count($traits)) {
                foreach($traits as $trait) {
                    if("Iono\\Console\\Traits\\ComponentTrait" == $trait->getName()) {
                        /** @var  $start */
                        $start = microtime(true);
                        $appProperty = $reflectionClass->getProperty('app');
                        $appProperty->setAccessible(true);
                        $appProperty->setValue($class, $application);
                        $class->action($query);
                        /** @var  $end */
                        $end = microtime(true);
                        $process = sprintf('%0.5f', ($end - $start));
                        $output->writeln("<info>{$process}</info><comment>/second</comment>");
                    }
                }
            }
        }
    }
}