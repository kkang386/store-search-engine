<?php

namespace App\Providers;

use App\Models\Product;
use App\Observers\ProductObserver;
use App\Repositories\Contracts\SearchRepositoryInterface;
use App\Repositories\ElasticsearchRepository;
use App\Services\Admin\AnalyticsService;
use App\Services\Admin\CampaignService;
use App\Services\Admin\QueryRuleService;
use App\Services\Admin\SynonymService;
use App\Services\Benchmark\BenchmarkReportGenerator;
use App\Services\Benchmark\BenchmarkService;
use App\Services\Benchmark\MetricsCalculator;
use App\Services\Search\CategoryService;
use App\Services\Search\FacetBuilder;
use App\Services\Search\IndexingService;
use App\Services\Search\QueryBuilder;
use App\Services\Search\SearchService;
use App\Services\Search\SuggestQueryBuilder;
use App\Services\Search\SuggestService;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;

class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            $config = config('elasticsearch');
            $builder = ClientBuilder::create()
                ->setHosts($config['hosts'])
                ->setHttpClient(new \GuzzleHttp\Client([
                    'timeout'         => $config['timeout'],
                    'connect_timeout' => $config['connect_timeout'],
                ]));

            if (!empty($config['user'])) {
                $builder->setBasicAuthentication($config['user'], $config['pass']);
            }

            return $builder->build();
        });

        $this->app->singleton(ElasticsearchRepository::class, function ($app) {
            return new ElasticsearchRepository($app->make(Client::class));
        });

        $this->app->bind(SearchRepositoryInterface::class, ElasticsearchRepository::class);

        $this->app->singleton(QueryBuilder::class);
        $this->app->singleton(FacetBuilder::class);
        $this->app->singleton(SuggestQueryBuilder::class);
        $this->app->singleton(IndexingService::class);
        $this->app->singleton(QueryRuleService::class);
        $this->app->singleton(CampaignService::class);

        $this->app->singleton(SearchService::class, function ($app) {
            return new SearchService(
                $app->make(ElasticsearchRepository::class),
                $app->make(QueryBuilder::class),
                $app->make(FacetBuilder::class),
                $app->make(QueryRuleService::class),
                $app->make(CampaignService::class),
                $app->make(CategoryService::class),
            );
        });

        $this->app->singleton(SuggestService::class, function ($app) {
            return new SuggestService(
                $app->make(ElasticsearchRepository::class),
                $app->make(SuggestQueryBuilder::class),
                $app->make(QueryRuleService::class),
                $app->make(CategoryService::class),
            );
        });

        $this->app->singleton(SynonymService::class, function ($app) {
            return new SynonymService($app->make(IndexingService::class));
        });

        $this->app->singleton(AnalyticsService::class, function ($app) {
            return new AnalyticsService($app->make(ElasticsearchRepository::class));
        });

        $this->app->singleton(MetricsCalculator::class);
        $this->app->singleton(BenchmarkReportGenerator::class);

        $this->app->singleton(BenchmarkService::class, function ($app) {
            return new BenchmarkService(
                $app->make(SearchService::class),
                $app->make(SuggestService::class),
                $app->make(MetricsCalculator::class),
                $app->make(BenchmarkReportGenerator::class),
            );
        });
    }

    public function boot(): void
    {
        Product::observe(ProductObserver::class);
    }
}
