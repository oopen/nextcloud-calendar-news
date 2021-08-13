<?php
/**
 * @copyright Copyright (c) 2020 Marco Ziech <marco+nc@ziech.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\CalendarNews\Job;

use OCA\CalendarNews\Service\ScheduleService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class MailJob extends TimedJob {

    /**
     * @var ScheduleService
     */
    private $scheduleService;
    /**
     * @var IUserManager
     */
    private $userManager;
    /**
     * @var IUserSession
     */
    private $userSession;
    /**
     * @var LoggerInterface
     */
    private $logger;

    function __construct(
        ITimeFactory $time,
        IUserManager $userManager,
        IUserSession $userSession,
        ScheduleService $scheduleService,
        LoggerInterface $logger
    ) {
        parent::__construct($time);
        parent::setInterval(20 * 60 /* s */);
        $this->scheduleService = $scheduleService;
        $this->logger = $logger;
        $this->userManager = $userManager;
        $this->userSession = $userSession;
    }

    protected function run($argument) {
        $oldUser = $this->userSession->getUser();
        try {
            $next = $this->scheduleService->getNextExecutionTime();
            if ($next == null) {
                return;
            }
            $now = new \DateTime();

            if ($next < $now) {
                $this->findSuitableUser();

                $this->logger->info("Sending newsletter is due: {$next->format("c")} < {$now->format("c")}");
                $this->scheduleService->setLastExecutionTime($now);
                $this->scheduleService->sendNow();
            }
        } finally {
            $this->userSession->setUser($oldUser);
        }
    }

    // TODO: make newsletter config user-specific so we don't need this ugly hack *sigh*
    private function findSuitableUser(): void {
        foreach ($this->userManager->search("") as $user) {
            $this->userSession->setUser($user);
            // just find first user with access to all required calendars, it doesn't really matter
            $this->logger->info("Checking whether {$user->getUID()} is suitable to send newsletter");
            if ($this->scheduleService->isSuitableUser()) {
                $this->logger->info("Sending newsletter as user: {$user->getUID()}");
                return;
            }
        }
        throw new \RuntimeException("No suitable user found for sending newsletter");
    }

}
