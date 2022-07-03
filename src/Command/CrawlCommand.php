<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CrawlCommand extends Command
{
    const DEFINED_SITE_NAMES = [
        'master-lux' => 'https://master-lux.md/ru/search?search-for=tyres&width=&height=&radius=&speed-index=&brand=',
        'coleso' => 'https://coleso.md/anvelope_auto/autoturism/'
    ];

    private HttpClientInterface $client;

    private string $projectDir;

    public function __construct(HttpClientInterface $client, string $projectDir, string $name = null)
    {
        parent::__construct($name);
        $this->client = $client;
        $this->projectDir = $projectDir;
    }

    protected function configure(): void
    {
        $this
            ->setName('parse')
            ->addArgument('size', InputArgument::OPTIONAL)
            ->addArgument('radius', InputArgument::OPTIONAL);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $date = new \DateTime();
        $timestamp = $date->getTimestamp();
        $outputDir = $this->projectDir . '/output';
        if (!is_dir($outputDir)) {
            mkdir($outputDir);
        }

        $size = $input->getArgument('size');
        $radius = $input->getArgument('radius');

        $result = [];
        foreach (self::DEFINED_SITE_NAMES as $site => $url) {
            if ('master-lux' === $site) {
                continue;
            }
            $output->writeln('Обработка сайта ' . $site . '...');

            $response = $this->client->request('GET', $url);
            $crawler = new Crawler($response->getContent());

            if ($site === 'coleso') {
                $result = $this->colesomd($output, $url, $radius, $size);
            } else {
                $result = $this->masterLux($crawler);
            }

            $count = count($result);
            $output->writeln("Найдено {$count} товаров на сайте " . $site);

            if (0 === $count) {
                continue;
            }

            $filename = $site . '-' . $timestamp . '.csv';
            $fp = fopen($outputDir . '/' . $filename, 'w');

            foreach ($result as $fields) {
                fputcsv($fp, $fields);
            }

            fclose($fp);
        }

        return self::SUCCESS;
    }

    protected function masterLux(Crawler $crawler): array
    {
        $cards = $crawler->filter('.caption');

        return $cards->each(function ($node) {
            return [
                'title' => $node->filter('a > p')->text(),
                'price' => $node->filter('p > button')->text(),
            ];
        });
    }

    protected function filterColesomd(array $found, ?int $radius, ?string $size): array
    {
        if (null === $radius && null === $size) {
            return $found;
        }

        $result = [];

        foreach ($found as $value) {
            $title = $value['title'];
            $price = $value['price'];

            if (
                !is_null($radius) && str_contains($title, 'R' . $radius) ||
                !is_null($size) && str_contains($title, $size)
            ) {
                $result[] = [
                    'title' => $title,
                    'price' => $price
                ];
            }
        }

        return $result;
    }

    protected function colesomd(OutputInterface $output, string $url, ?int $radius, ?string $size): array
    {
        $parseResult = $this->parseColesoMd($url);
        $cardData = $parseResult['result'];
        $pagination = $parseResult['pagination'];

        if (0 === $pagination->count()) {
            return $this->filterColesomd($cardData, $radius, $size);
        }

        $maxPage = $this->getMaxPageColesomd($pagination);

        for ($i = 2; $i <= $maxPage; $i++) {
            $pageUrl = $url . '&page=' . $i;
            $output->writeln('Сборка данных со страницы #' . $i);

            $parseResult = $this->parseColesoMd($pageUrl);
            $newCardData = $parseResult['result'];
            $pagination = $parseResult['pagination'];
            $cardData = array_merge($cardData, $newCardData);

            $output->writeln('Собрано: ' . count($cardData) . ' товаров');

            $maxPage = $this->getMaxPageColesomd($pagination);
        }

        return $this->filterColesomd($cardData, $radius, $size);
    }

    protected function getMaxPageColesomd(Crawler $pagination): int
    {
        $pages = $pagination->filter('li');
        $pagesNumbers = $pages->each(function ($node) {
            try {
                $result = $node->filter('a')->text();
            } catch (\Exception $e) {
                $result = $node->filter('span')->text();
            }

            if (preg_match("/^[0-9]+$/", $result)) {
                return $result;
            }

            return null;
        });

        return max($pagesNumbers);
    }

    protected function parseColesoMd(string $url): array
    {
        $response = $this->client->request('GET', $url);
        $crawler = new Crawler($response->getContent());

        $cards = $crawler->filter('.product-thumb');
        $pagination = $crawler->filter('.pagination');

        return [
            'pagination' => $pagination,
            'result' => $cards->each(function ($node) {
                return [
                    'title' => $node->filter('div > div > h4')->text(),
                    'price' => $node->filter('div > div > .price')->text(null)
                ];
            })
        ];
    }
}