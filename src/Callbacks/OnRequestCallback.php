<?php

namespace VrtakCZ\NewRelic\Nette\Callbacks;

use Nette\Application\Application;
use Nette\Application\Request;
use Nette\Application\UI\Presenter;
use Nette\Utils\Strings;
use VrtakCZ\NewRelic\Tracy\Bootstrap;

class OnRequestCallback
{

	/** @var string[] */
	private $map;

	/** @var string */
	private $license;

	/** @var string */
	private $actionKey;

	/**
	 * @param string[] $map
	 * @param string $license
	 * @param string $actionKey
	 */
	public function __construct(array $map, $license, $actionKey = Presenter::ACTION_KEY)
	{
		$this->map = $map;
		$this->license = $license;
		$this->actionKey = $actionKey;
	}

	public function __invoke(Application $application, Request $request)
	{
		$params = $request->getParameters();
		$action = $request->getPresenterName();

		if (isset($params[$this->actionKey])) {
			$action = \sprintf('%s:%s', $action, $params[$this->actionKey]);
		}

		if (!empty($this->map)) {
			foreach ($this->map as $pattern => $appName) {
				if ($pattern === '*') {
					continue;
				}

				if (Strings::endsWith($pattern, '*')) {
					$pattern = Strings::substring($pattern, 0, -1);
				}

				if (Strings::startsWith($pattern, ':')) {
					$pattern = Strings::substring($pattern, 1);
				}

				if (Strings::startsWith($action, $pattern)) {
					Bootstrap::setup($appName, $this->license);

					break;
				}
			}
		}

		if (PHP_SAPI === 'cli') {
			\newrelic_background_job();
			\newrelic_name_transaction('$ ' . \basename($_SERVER['argv'][0]) . ' ' . \implode(' ', \array_slice($_SERVER['argv'], 1)));
		} else {
			\newrelic_name_transaction($action);
			\newrelic_disable_autorum();
		}
	}

	public function register(Application $application): void
	{
		$application->onRequest[] = $this;
	}

}
