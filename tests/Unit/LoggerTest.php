<?php

namespace eRede\Tests\Unit;

use eRede\Support\Config;
use eRede\Support\Logger;
use Illuminate\Container\Container;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;

class LoggerTest extends TestCase
{
    private function config(array $logging): Config
    {
        return new Config(pv: 'pv', token: 'token', logging: $logging);
    }

    /** Stub de LogManager: implementa LoggerInterface e expõe channel(). */
    private function logManager(array $canaisValidos = ['erede']): object
    {
        return new class($canaisValidos) extends AbstractLogger
        {
            public array $records = [];

            public array $canaisPedidos = [];

            public function __construct(private array $canaisValidos) {}

            public function channel(string $nome): self
            {
                $this->canaisPedidos[] = $nome;

                if (! in_array($nome, $this->canaisValidos, true)) {
                    throw new InvalidArgumentException("Log [{$nome}] is not defined.");
                }

                return $this;
            }

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = compact('level', 'message', 'context');
            }
        };
    }

    #[Test]
    public function logging_desabilitado_devolve_null_logger(): void
    {
        $container = new Container;
        $container->instance('log', $this->logManager());

        $logger = Logger::resolve($this->config(['enabled' => false, 'channel' => 'erede']), $container);

        $this->assertInstanceOf(NullLogger::class, $logger);
    }

    #[Test]
    public function container_sem_binding_de_log_devolve_null_logger(): void
    {
        $logger = Logger::resolve($this->config(['enabled' => true, 'channel' => 'erede']), new Container);

        $this->assertInstanceOf(NullLogger::class, $logger);
    }

    #[Test]
    public function usa_o_canal_configurado(): void
    {
        $manager = $this->logManager(['erede']);
        $container = new Container;
        $container->instance('log', $manager);

        $logger = Logger::resolve($this->config(['enabled' => true, 'channel' => 'erede']), $container);
        $logger->error('oi');

        $this->assertSame(['erede'], $manager->canaisPedidos);
        $this->assertCount(1, $manager->records);
    }

    #[Test]
    public function canal_inexistente_cai_para_o_logger_padrao_sem_estourar(): void
    {
        $manager = $this->logManager(canaisValidos: []);
        $container = new Container;
        $container->instance('log', $manager);

        $logger = Logger::resolve($this->config(['enabled' => true, 'channel' => 'inexistente']), $container);
        $logger->error('ainda registra');

        $this->assertSame($manager, $logger);
        $this->assertCount(1, $manager->records);
    }

    #[Test]
    public function canal_vazio_usa_o_logger_padrao(): void
    {
        $manager = $this->logManager();
        $container = new Container;
        $container->instance('log', $manager);

        $logger = Logger::resolve($this->config(['enabled' => true, 'channel' => '  ']), $container);

        $this->assertSame($manager, $logger);
        $this->assertSame([], $manager->canaisPedidos);
    }

    #[Test]
    public function manager_sem_metodo_channel_e_usado_diretamente(): void
    {
        $simples = new class extends AbstractLogger
        {
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = $message;
            }
        };

        $container = new Container;
        $container->instance('log', $simples);

        $logger = Logger::resolve($this->config(['enabled' => true, 'channel' => 'erede']), $container);

        $this->assertSame($simples, $logger);
    }
}
