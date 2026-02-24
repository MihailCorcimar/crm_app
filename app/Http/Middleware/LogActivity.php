<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class LogActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! auth()->check()) {
            return $response;
        }

        if ($request->isMethod('HEAD') || $request->route() === null) {
            return $response;
        }

        if (! Schema::hasTable('activity_logs')) {
            return $response;
        }

        $routeName = (string) $request->route()->getName();
        if ($routeName === '' || str_starts_with($routeName, 'logs.')) {
            return $response;
        }

        ActivityLog::query()->create([
            'occurred_at' => now(),
            'user_id' => auth()->id(),
            'menu' => $this->menuName($request),
            'action' => $this->actionName($request),
            'device' => $this->deviceType($request),
            'ip_address' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'user_agent' => $request->userAgent(),
        ]);

        return $response;
    }

    private function actionName(Request $request): string
    {
        $actionMethod = $request->route()?->getActionMethod();

        return match ($actionMethod) {
            'store' => 'Create',
            'update' => 'Update',
            'destroy' => 'Delete',
            'index', 'show', 'create', 'edit' => 'Read',
            default => match ($request->method()) {
                'POST' => 'Create',
                'PUT', 'PATCH' => 'Update',
                'DELETE' => 'Delete',
                default => 'Read',
            },
        };
    }

    private function menuName(Request $request): string
    {
        $routeName = (string) $request->route()?->getName();
        $prefix = explode('.', $routeName)[0] ?? 'Unknown';

        return match ($prefix) {
            'entities' => 'Clientes / Fornecedores',
            'contacts' => 'Contactos',
            'proposals' => 'Propostas',
            'orders' => 'Encomendas',
            'supplier-orders' => 'Encomendas - Fornecedores',
            'supplier-invoices' => 'Faturas Fornecedor',
            'calendar' => 'Calendario',
            'access' => 'Gestao de Acessos',
            'settings' => 'Configuracoes',
            default => ucfirst($prefix),
        };
    }

    private function deviceType(Request $request): string
    {
        $agent = strtolower((string) $request->userAgent());

        return str_contains($agent, 'mobile') ? 'Mobile' : 'Desktop';
    }
}
