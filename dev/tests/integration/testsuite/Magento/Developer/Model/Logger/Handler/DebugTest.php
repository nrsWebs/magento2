<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Developer\Model\Logger\Handler;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Logger\Monolog;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Deploy\Model\Mode;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Preconditions
 *  - Developer mode enabled
 *  - Log file isn't exists
 *  - 'Log to file' setting are enabled
 *
 * Test steps
 *  - Enable production mode without compilation
 *  - Try to log message into log file
 *  - Assert that log file isn't exists
 *  - Assert that 'Log to file' setting are disabled
 *
 *  - Enable 'Log to file' setting
 *  - Try to log message into debug file
 *  - Assert that log file is exists
 *  - Assert that log file contain logged message
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DebugTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Monolog
     */
    private $logger;

    /**
     * @var Mode
     */
    private $mode;

    /**
     * @var InputInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $inputMock;

    /**
     * @var OutputInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $outputMock;

    /**
     * @var WriteInterface
     */
    private $etcDirectory;

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function setUp()
    {
        /** @var Filesystem $filesystem */
        $filesystem = Bootstrap::getObjectManager()->create(Filesystem::class);
        $this->etcDirectory = $filesystem->getDirectoryWrite(DirectoryList::CONFIG);
        $this->etcDirectory->copyFile('env.php', 'env.base.php');

        $this->inputMock = $this->getMockBuilder(InputInterface::class)
            ->getMockForAbstractClass();
        $this->outputMock = $this->getMockBuilder(OutputInterface::class)
            ->getMockForAbstractClass();
        $this->logger = Bootstrap::getObjectManager()->get(Monolog::class);
        $this->mode = Bootstrap::getObjectManager()->create(
            Mode::class,
            [
                'input' => $this->inputMock,
                'output' => $this->outputMock
            ]
        );
    }

    /**
     * @inheritdoc
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function tearDown()
    {
        $this->etcDirectory->delete('env.php');
        $this->etcDirectory->renameFile('env.base.php', 'env.php');
    }

    /**
     * @throws \Exception
     */
    public function testDebugInProductionMode()
    {
        $message = 'test message';

        $this->mode->enableDeveloperMode();
        if (file_exists($this->getDebuggerLogPath())) {
            unlink($this->getDebuggerLogPath());
        }

        $this->logger->debug($message);
        $this->assertFileExists($this->getDebuggerLogPath());
        $this->assertContains($message, file_get_contents($this->getDebuggerLogPath()));

        unlink($this->getDebuggerLogPath());
        $this->mode->enableProductionModeMinimal();

        $this->logger->debug($message);
        $this->assertFileNotExists($this->getDebuggerLogPath());
    }

    /**
     * @return bool|string
     */
    private function getDebuggerLogPath()
    {
        foreach ($this->logger->getHandlers() as $handler) {
            if ($handler instanceof Debug) {
                return $handler->getUrl();
            }
        }
        return false;
    }
}
