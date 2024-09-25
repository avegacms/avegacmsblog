<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EnvTest extends CIUnitTestCase
{
    public function testEventBusVhostIsSetCorrectly(): void
    {
        // Загружаем значение переменной окружения для eventbus.vhost
        $vhost = getenv('eventbus.vhost');

        // Проверяем, что оно соответствует ожидаемому значению
        $this->assertEquals('my_vhost', $vhost);
    }

    public function testEventBusQueuesIsSetCorrectly(): void
    {
        // Загружаем значение переменной окружения для eventbus.queues
        $queues = getenv('eventbus.queues');

        // Проверяем, что оно соответствует ожидаемому значению
        $this->assertEquals('q_1', $queues);
    }
}
