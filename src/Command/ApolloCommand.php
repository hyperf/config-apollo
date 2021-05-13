<?php


namespace Hyperf\ConfigApollo\Command;


use Hyperf\Command\Command;
use Hyperf\ConfigApollo\ClientInterface;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Utils\ApplicationContext;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Input\InputInterface;

class ApolloCommand extends Command
{

    /**
     * @var ConfigInterface
     */
    private $config;
    /**
     * @var ClientInterface|mixed
     */
    private $client;
    /**
     * @var StdoutLoggerInterface|mixed
     */
    private $logger;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct('apollo:pull-env');
        $this->client = $container->get(ClientInterface::class);
        $this->config = $container->get(ConfigInterface::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);
    }

    public function enableDispatcher(InputInterface $input)
    {
        $this->eventDispatcher = ApplicationContext::getContainer()->get(EventDispatcherInterface::class);
    }

    private function isEnable(): bool
    {
        return $this->config->get('apollo.enable', false)
            && count($this->config->get('apollo.env_namespace', [])) > 0;
    }

    public function handle()
    {
        if ($this->isEnable()) {
            $namespaces = $this->config->get('apollo.env_namespace', []);
            $callbacks = [];
            $callback = function ($configs, $namespace) {
                $conf = [];
                foreach ($configs['configurations'] as $key => $value) {
                    $conf[] = sprintf('%s=%s', $key, $value);
                }
                $fileContent = "\n#namespace:$namespace\n".implode(PHP_EOL, $conf)."\n";
                file_put_contents(BASE_PATH . '/.env', $fileContent, FILE_APPEND | LOCK_EX);
            };
            foreach ($namespaces as $namespace) {
                if (is_string($namespace)) {
                    $callbacks[$namespace] = $callback;
                }
            }
            $this->client->pull($namespaces, $callbacks);
        }
    }
}