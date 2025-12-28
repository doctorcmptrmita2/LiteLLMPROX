<?php

namespace App\Providers;

use App\Models\Project;
use App\Policies\ProjectPolicy;
use App\Services\Llm\LiteLlmClient;
use App\Services\Llm\Agents\TriageAgent;
use App\Services\Llm\Agents\PlannerAgent;
use App\Services\Llm\Agents\CodingAgent;
use App\Services\Llm\Agents\ReviewAgent;
use App\Services\Llm\Agents\TestAgent;
use App\Services\Llm\Routing\BudgetClassifier;
use App\Services\Llm\Routing\RiskScorer;
use App\Services\Llm\Routing\ModelSelector;
use App\Services\Llm\Quality\QualityGateEnforcer;
use App\Services\Llm\Pipeline\PipelineOrchestrator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register LiteLLM Client as singleton
        $this->app->singleton(LiteLlmClient::class, function ($app) {
            return new LiteLlmClient();
        });
        
        // Register Routing services
        $this->app->singleton(BudgetClassifier::class);
        $this->app->singleton(RiskScorer::class);
        
        $this->app->singleton(ModelSelector::class, function ($app) {
            return new ModelSelector(
                $app->make(BudgetClassifier::class),
                $app->make(RiskScorer::class)
            );
        });
        
        // Register Agents
        $this->app->singleton(TriageAgent::class, function ($app) {
            return new TriageAgent(
                $app->make(LiteLlmClient::class),
                $app->make(RiskScorer::class),
                $app->make(BudgetClassifier::class)
            );
        });
        
        $this->app->singleton(PlannerAgent::class, function ($app) {
            return new PlannerAgent($app->make(LiteLlmClient::class));
        });
        
        $this->app->singleton(CodingAgent::class, function ($app) {
            return new CodingAgent(
                $app->make(LiteLlmClient::class),
                $app->make(ModelSelector::class)
            );
        });
        
        $this->app->singleton(ReviewAgent::class, function ($app) {
            return new ReviewAgent(
                $app->make(LiteLlmClient::class),
                $app->make(RiskScorer::class)
            );
        });
        
        $this->app->singleton(TestAgent::class, function ($app) {
            return new TestAgent($app->make(LiteLlmClient::class));
        });
        
        // Register Quality Gates
        $this->app->singleton(QualityGateEnforcer::class);
        
        // Register Pipeline Orchestrator
        $this->app->singleton(PipelineOrchestrator::class, function ($app) {
            return new PipelineOrchestrator(
                $app->make(TriageAgent::class),
                $app->make(PlannerAgent::class),
                $app->make(CodingAgent::class),
                $app->make(ReviewAgent::class),
                $app->make(TestAgent::class),
                $app->make(QualityGateEnforcer::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register policies
        Gate::policy(Project::class, ProjectPolicy::class);
    }
}
