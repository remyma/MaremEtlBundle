<?php

namespace Marem\Bundle\EtlBundle\Command;


use Marem\Bundle\EtlBundle\Entity\Job;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class JobCommand extends ContainerAwareCommand {

    const STATUS_STARTED = 'started';

    const STATUS_READY = 'ready';

    const STATUS_TERMINATED = 'terminated';

    private $items;

    /**
     * @var $job Job
     */
    private $job;

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<comment>Start Job '.$this->getName().' </comment>');
        $this->beforeJob();

        $output->writeln('<comment>Total : '.count($this->getItems()).'</comment>');
        $progress = $this->getHelper('progress');
        $progress->start($output, count($this->getItems()));
        $progress->setBarCharacter('<comment>=</comment>');
        $progress->setRedrawFrequency(20);

        $i = 1;
        foreach ($this->getItems() as $item) {

            $this->transformItem($item, $output);

            if (0 === $i % 20) {
                $this->getEntityManager()->flush();
            }

            $progress->advance();

            $i++;
        }

        $this->afterJob();
        $output->writeln('');
        $output->writeln('<comment>End Job</comment>');
    }

    /**
     * @return array
     */
    protected abstract function extractItems();

    protected abstract function transformItem($item, OutputInterface $output);

    protected function beforeJob() {
        $em = $this->getEntityManager();
        $this->job = $em->getRepository('MaremEtlBundle:Job')->findOneBy(array('name' => $this->getName()));

        if ($this->job == null) {
            $job = new Job();
            $job->setName($this->getName());
            $job->setStatus(self::STATUS_STARTED);
            $job->setProcessed(0);
            $job->setTotal(count($this->getItems()));
            $em->persist($job);
            $em->flush();

            $this->job = $job;
        }

        $em->flush();
    }

    protected function afterJob() {
        $em = $this->getEntityManager();
        $this->job->setStatus(self::STATUS_TERMINATED);

        $em->flush();
    }

    private function getItems()
    {
        if (count($this->items) == 0) {
            $this->items = $this->extractItems();
        }

        return $this->items;
    }

    private function getEntityManager() {
        return $this->getContainer()->get('doctrine.orm.entity_manager');
    }

}