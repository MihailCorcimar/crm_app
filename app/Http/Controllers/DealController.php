<?php

namespace App\Http\Controllers;

use App\Http\Requests\DealProposalUploadRequest;
use App\Http\Requests\DealProductRequest;
use App\Http\Requests\DealQuickActivityRequest;
use App\Http\Requests\DealRequest;
use App\Http\Requests\DealSendProposalEmailRequest;
use App\Http\Requests\DealStageRequest;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\DealProduct;
use App\Models\DealEmailLog;
use App\Models\Entity;
use App\Models\Item;
use App\Models\User;
use App\Mail\DealProposalMail;
use App\Support\DealFollowUpService;
use App\Support\DealStageService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DealController extends Controller
{
    public function __construct(
        private readonly DealStageService $dealStageService,
        private readonly DealFollowUpService $dealFollowUpService,
    )
    {
        $this->authorizeResource(Deal::class, 'deal');
    }

    public function index(Request $request): Response
    {
        $tenantId = TenantContext::id($request) ?? 0;

        $validated = $request->validate([
            'owner_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($tenantId): void {
                    $query->whereExists(function ($subQuery) use ($tenantId): void {
                        $subQuery
                            ->select(DB::raw(1))
                            ->from('tenant_user')
                            ->whereColumn('tenant_user.user_id', 'users.id')
                            ->where('tenant_user.tenant_id', $tenantId);
                    });
                }),
            ],
            'expected_close_from' => ['nullable', 'date'],
            'expected_close_to' => ['nullable', 'date', 'after_or_equal:expected_close_from'],
            'value_min' => ['nullable', 'numeric', 'min:0'],
            'value_max' => ['nullable', 'numeric', 'gte:value_min'],
        ]);

        $stageOptions = $this->stageOptions();
        $filters = $this->normalizedFilters($validated);

        $deals = Deal::query()
            ->with(['entity:id,name', 'owner:id,name'])
            ->when(
                $filters['owner_id'] !== null,
                fn ($query) => $query->where('owner_id', $filters['owner_id'])
            )
            ->when(
                $filters['expected_close_from'] !== null,
                fn ($query) => $query->whereDate('expected_close_date', '>=', $filters['expected_close_from'])
            )
            ->when(
                $filters['expected_close_to'] !== null,
                fn ($query) => $query->whereDate('expected_close_date', '<=', $filters['expected_close_to'])
            )
            ->when(
                $filters['value_min'] !== null,
                fn ($query) => $query->where('value', '>=', $filters['value_min'])
            )
            ->when(
                $filters['value_max'] !== null,
                fn ($query) => $query->where('value', '<=', $filters['value_max'])
            )
            ->orderByDesc('updated_at')
            ->get();

        $columns = collect($stageOptions)
            ->map(function (array $stageOption) use ($deals): array {
                $stageDeals = $deals
                    ->filter(fn (Deal $deal): bool => $deal->stage === $stageOption['value'])
                    ->values();

                $cards = $stageDeals
                    ->map(fn (Deal $deal): array => $this->dealCardPayload($deal))
                    ->all();

                return [
                    'stage' => $stageOption['value'],
                    'label' => $stageOption['label'],
                    'count' => count($cards),
                    'total_value' => (float) $stageDeals->sum(fn (Deal $deal): float => (float) $deal->value),
                    'deals' => $cards,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('deals/Index', [
            'columns' => $columns,
            'stageOptions' => $stageOptions,
            'owners' => $this->owners(),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('deals/Create', [
            'entities' => $this->entities(),
            'owners' => $this->owners(),
            'stageOptions' => $this->stageOptions(),
            'defaultOwnerId' => auth()->id(),
        ]);
    }

    public function store(DealRequest $request): RedirectResponse
    {
        $deal = Deal::query()->create($this->payload($request->validated()));
        $this->syncFollowUpOnStageTransition($deal, null, $deal->stage);

        return to_route('deals.index');
    }

    public function show(Deal $deal): Response
    {
        $deal->load([
            'entity:id,name',
            'owner:id,name',
            'proposalUploader:id,name',
            'products.item:id,name,description,reference,code',
        ]);

        return Inertia::render('deals/Show', [
            'deal' => [
                'id' => $deal->id,
                'title' => $deal->title,
                'entity' => $deal->entity?->name,
                'stage' => $deal->stage,
                'value' => (float) $deal->value,
                'probability' => (int) $deal->probability,
                'expected_close_date' => $deal->expected_close_date?->format('Y-m-d'),
                'owner' => $deal->owner?->name,
                'created_at' => $deal->created_at?->format('d/m/Y H:i'),
                'updated_at' => $deal->updated_at?->format('d/m/Y H:i'),
                'proposal' => $this->proposalPayload($deal),
            ],
            'dealProducts' => $this->dealProductsPayload($deal),
            'productOptions' => $this->productOptions(),
            'timeline' => $this->timeline($deal),
            'quickActivityTypes' => $this->quickActivityTypes(),
            'quickActivityDefaults' => [
                'activity_type' => 'call',
                'activity_at' => now()->format('Y-m-d\TH:i'),
                'owner_id' => auth()->id(),
            ],
            'proposalEmailDefaults' => $this->proposalEmailDefaults($deal),
            'followUp' => $this->followUpPayload($deal),
            'owners' => $this->owners(),
        ]);
    }

    public function storeProposal(DealProposalUploadRequest $request, Deal $deal): RedirectResponse
    {
        $file = $request->file('proposal_file');
        if ($file === null) {
            return back();
        }

        if (is_string($deal->proposal_path) && $deal->proposal_path !== '') {
            Storage::disk('local')->delete($deal->proposal_path);
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $filename = sprintf('%s-%s.%s', now()->format('YmdHis'), Str::uuid(), $extension);
        $path = $file->storeAs(sprintf('deals/%d/proposals', $deal->id), $filename, 'local');

        $deal->update([
            'proposal_path' => $path,
            'proposal_original_name' => $file->getClientOriginalName(),
            'proposal_mime_type' => $file->getClientMimeType(),
            'proposal_size' => $file->getSize(),
            'proposal_uploaded_at' => now(),
            'proposal_uploaded_by' => auth()->id(),
        ]);

        return back();
    }

    public function downloadProposal(Deal $deal): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('view', $deal);

        abort_unless(is_string($deal->proposal_path) && $deal->proposal_path !== '', 404);
        abort_unless(Storage::disk('local')->exists($deal->proposal_path), 404);

        return Storage::disk('local')->download(
            $deal->proposal_path,
            $deal->proposal_original_name ?: basename($deal->proposal_path)
        );
    }

    public function sendProposalEmail(DealSendProposalEmailRequest $request, Deal $deal): RedirectResponse
    {
        $this->authorize('update', $deal);

        if (! is_string($deal->proposal_path) || $deal->proposal_path === '') {
            return back()->withErrors([
                'proposal_file' => 'Carrega primeiro uma proposta antes de enviar por email.',
            ]);
        }

        if (! Storage::disk('local')->exists($deal->proposal_path)) {
            return back()->withErrors([
                'proposal_file' => 'O ficheiro da proposta nao foi encontrado. Volta a carregar a proposta.',
            ]);
        }

        $validated = $request->validated();
        $subject = trim((string) $validated['subject']);
        $body = trim((string) $validated['body']);

        try {
            Mail::to((string) $validated['to_email'])->send(
                new DealProposalMail(
                    subjectLine: $subject,
                    bodyText: $body,
                    proposalPath: $deal->proposal_path,
                    proposalName: $deal->proposal_original_name ?: basename($deal->proposal_path),
                    proposalMimeType: $deal->proposal_mime_type,
                )
            );
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'proposal_email' => 'Nao foi possivel enviar o email da proposta. Verifica a configuracao de email.',
            ]);
        }

        DealEmailLog::query()->create([
            'deal_id' => $deal->id,
            'email_type' => 'proposal',
            'to_email' => (string) $validated['to_email'],
            'subject' => $subject,
            'body' => $body,
            'attachment_name' => $deal->proposal_original_name ?: basename($deal->proposal_path),
            'sent_by' => auth()->id(),
            'sent_at' => now(),
        ]);

        return back()->with('success', 'Proposta enviada por email com sucesso.');
    }

    public function edit(Deal $deal): Response
    {
        return Inertia::render('deals/Edit', [
            'deal' => [
                'id' => $deal->id,
                'entity_id' => $deal->entity_id,
                'title' => $deal->title,
                'stage' => $deal->stage,
                'value' => (float) $deal->value,
                'probability' => (int) $deal->probability,
                'expected_close_date' => $deal->expected_close_date?->format('Y-m-d'),
                'owner_id' => $deal->owner_id,
            ],
            'entities' => $this->entities(),
            'owners' => $this->owners(),
            'stageOptions' => $this->stageOptions(),
        ]);
    }

    public function update(DealRequest $request, Deal $deal): RedirectResponse
    {
        $fromStage = $deal->stage;
        $deal->update($this->payload($request->validated()));
        $this->syncFollowUpOnStageTransition($deal->fresh(), $fromStage, $deal->stage);

        return to_route('deals.index');
    }

    public function updateStage(DealStageRequest $request, Deal $deal): RedirectResponse
    {
        $fromStage = $deal->stage;
        $toStage = $request->string('stage')->toString();

        $deal->update([
            'stage' => $toStage,
        ]);
        $this->syncFollowUpOnStageTransition($deal->fresh(), $fromStage, $toStage);

        return back();
    }

    public function cancelFollowUp(Deal $deal): RedirectResponse
    {
        $this->authorize('update', $deal);
        $this->dealFollowUpService->stop($deal, DealFollowUpService::STOP_MANUAL);

        return back()->with('success', 'Follow up cancelado.');
    }

    public function resumeFollowUp(Deal $deal): RedirectResponse
    {
        $this->authorize('update', $deal);

        if ($deal->stage !== Deal::STAGE_FOLLOW_UP) {
            return back()->withErrors([
                'follow_up' => 'Para retomar, o negócio tem de estar na etapa Follow Up.',
            ]);
        }

        $this->dealFollowUpService->start($deal);

        return back()->with('success', 'Follow up retomado.');
    }

    public function markCustomerReplied(Deal $deal): RedirectResponse
    {
        $this->authorize('update', $deal);
        $this->dealFollowUpService->markCustomerReplied($deal);

        return back()->with('success', 'Follow up parado: cliente respondeu.');
    }

    public function storeQuickActivity(DealQuickActivityRequest $request, Deal $deal): RedirectResponse
    {
        $validated = $request->validated();
        $activityType = (string) $validated['activity_type'];
        $startAt = CarbonImmutable::parse((string) $validated['activity_at']);
        $duration = $this->quickActivityDuration($activityType);
        $endAt = $startAt->addMinutes($duration);
        $title = trim((string) ($validated['title'] ?? ''));

        CalendarEvent::query()->create([
            'title' => $title !== '' ? $title : $this->quickActivityDefaultTitle($activityType, $deal),
            'description' => $validated['description'] ?? null,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'location' => null,
            'owner_id' => (int) $validated['owner_id'],
            'eventable_type' => Deal::class,
            'eventable_id' => $deal->id,
            'calendar_type_id' => null,
            'calendar_action_id' => null,
            'status' => 'active',
            // Keep legacy columns synchronized while they exist.
            'event_date' => $startAt->format('Y-m-d'),
            'event_time' => $startAt->format('H:i:s'),
            'duration_minutes' => $duration,
            'user_id' => (int) $validated['owner_id'],
            'entity_id' => $deal->entity_id,
            'share' => null,
            'knowledge' => $activityType,
        ]);

        return to_route('deals.show', $deal);
    }

    public function storeProduct(DealProductRequest $request, Deal $deal): RedirectResponse
    {
        $this->authorize('update', $deal);

        $validated = $request->validated();
        $itemId = (int) $validated['item_id'];
        $quantityToAdd = round((float) $validated['quantity'], 2);
        $unitPrice = round((float) $validated['unit_price'], 2);

        $existingLine = $deal->products()
            ->where('item_id', $itemId)
            ->first();

        if ($existingLine !== null) {
            $newQuantity = round((float) $existingLine->quantity + $quantityToAdd, 2);
            $existingLine->update([
                'quantity' => $newQuantity,
                'unit_price' => $unitPrice,
                'total_value' => round($newQuantity * $unitPrice, 2),
            ]);

            return back()->with('success', 'Produto atualizado no negócio.');
        }

        $deal->products()->create([
            'item_id' => $itemId,
            'quantity' => $quantityToAdd,
            'unit_price' => $unitPrice,
            'total_value' => round($quantityToAdd * $unitPrice, 2),
        ]);

        return back()->with('success', 'Produto associado ao negócio.');
    }

    public function destroyProduct(Deal $deal, DealProduct $dealProduct): RedirectResponse
    {
        $this->authorize('update', $deal);

        abort_unless($dealProduct->deal_id === $deal->id, 404);

        $dealProduct->delete();

        return back()->with('success', 'Produto removido do negócio.');
    }

    public function destroy(Deal $deal): RedirectResponse
    {
        $deal->delete();

        return to_route('deals.index');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        return [
            'entity_id' => $validated['entity_id'] ?? null,
            'person_id' => null,
            'title' => $validated['title'],
            'stage' => $validated['stage'],
            'value' => $validated['value'],
            'probability' => $validated['probability'],
            'expected_close_date' => $validated['expected_close_date'] ?? null,
            'owner_id' => $validated['owner_id'],
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function entities(): array
    {
        return Entity::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Entity $entity): array => [
                'id' => $entity->id,
                'name' => $entity->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function owners(): array
    {
        $tenantId = TenantContext::id();

        if (! is_int($tenantId) || $tenantId <= 0) {
            return [];
        }

        return User::query()
            ->whereHas('tenants', fn ($query) => $query->where('tenants.id', $tenantId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, reference: string|null, code: string|null, default_price: float}>
     */
    private function productOptions(): array
    {
        return Item::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->orderBy('reference')
            ->get(['id', 'name', 'description', 'reference', 'code', 'price'])
            ->map(function (Item $item): array {
                $name = trim((string) $item->name);
                if ($name === '') {
                    $name = trim((string) $item->description);
                }

                if ($name === '') {
                    $name = sprintf('Produto #%d', $item->id);
                }

                return [
                    'id' => $item->id,
                    'name' => $name,
                    'reference' => $item->reference,
                    'code' => $item->code,
                    'default_price' => (float) $item->price,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function stageOptions(): array
    {
        return collect($this->dealStageService->forTenant(TenantContext::id()))
            ->map(fn (array $stage): array => [
                'value' => (string) $stage['value'],
                'label' => (string) $stage['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{owner_id: int|null, expected_close_from: string|null, expected_close_to: string|null, value_min: float|null, value_max: float|null}
     */
    private function normalizedFilters(array $validated): array
    {
        return [
            'owner_id' => isset($validated['owner_id']) ? (int) $validated['owner_id'] : null,
            'expected_close_from' => isset($validated['expected_close_from']) ? (string) $validated['expected_close_from'] : null,
            'expected_close_to' => isset($validated['expected_close_to']) ? (string) $validated['expected_close_to'] : null,
            'value_min' => isset($validated['value_min']) ? (float) $validated['value_min'] : null,
            'value_max' => isset($validated['value_max']) ? (float) $validated['value_max'] : null,
        ];
    }

    /**
     * @return array{id: int, title: string, entity: string|null, stage: string, value: float, probability: int, expected_close_date: string|null, owner: string|null}
     */
    private function dealCardPayload(Deal $deal): array
    {
        return [
            'id' => $deal->id,
            'title' => $deal->title,
            'entity' => $deal->entity?->name,
            'stage' => $deal->stage,
            'value' => (float) $deal->value,
            'probability' => (int) $deal->probability,
            'expected_close_date' => $deal->expected_close_date?->format('Y-m-d'),
            'owner' => $deal->owner?->name,
        ];
    }

    /**
     * @return array<int, array{id: int, item_id: int, item_name: string, item_reference: string|null, quantity: float, unit_price: float, total_value: float}>
     */
    private function dealProductsPayload(Deal $deal): array
    {
        return $deal->products
            ->map(function (DealProduct $line): array {
                $itemName = trim((string) ($line->item?->name ?? ''));
                if ($itemName === '') {
                    $itemName = trim((string) ($line->item?->description ?? ''));
                }

                if ($itemName === '') {
                    $itemName = sprintf('Produto #%d', (int) $line->item_id);
                }

                return [
                    'id' => $line->id,
                    'item_id' => (int) $line->item_id,
                    'item_name' => $itemName,
                    'item_reference' => $line->item?->reference,
                    'quantity' => (float) $line->quantity,
                    'unit_price' => (float) $line->unit_price,
                    'total_value' => (float) $line->total_value,
                ];
            })
            ->sortBy('item_name')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key: string, entry_type: string, activity_type: string|null, title: string, details: string, owner: string|null, occurred_at: string}>
     */
    private function timeline(Deal $deal): array
    {
        $items = [
            [
                'key' => sprintf('deal-created-%d', $deal->id),
                'entry_type' => 'negocio',
                'activity_type' => null,
                'title' => 'Negócio criado',
                'details' => sprintf('Registo criado com etapa "%s".', $this->stageLabel($deal->stage)),
                'owner' => $deal->owner?->name,
                'occurred_at' => $deal->created_at?->format('d/m/Y H:i') ?? '-',
                'sort_at' => $deal->created_at?->getTimestamp() ?? 0,
            ],
        ];

        if ($deal->updated_at !== null && $deal->updated_at->ne($deal->created_at)) {
            $items[] = [
                'key' => sprintf('deal-updated-%d', $deal->id),
                'entry_type' => 'negocio',
                'activity_type' => null,
                'title' => 'Negócio atualizado',
                'details' => 'Foram registadas alterações no negócio.',
                'owner' => $deal->owner?->name,
                'occurred_at' => $deal->updated_at->format('d/m/Y H:i'),
                'sort_at' => $deal->updated_at->getTimestamp(),
            ];
        }

        $activityItems = CalendarEvent::query()
            ->where('eventable_type', Deal::class)
            ->where('eventable_id', $deal->id)
            ->with('owner:id,name')
            ->orderByDesc('start_at')
            ->limit(100)
            ->get()
            ->map(function (CalendarEvent $event): array {
                $activityType = is_string($event->knowledge) ? $event->knowledge : null;
                $timestamp = $event->start_at ?? $event->created_at;

                return [
                    'key' => sprintf('event-%d', $event->id),
                    'entry_type' => 'atividade',
                    'activity_type' => $activityType,
                    'title' => trim((string) ($event->title ?? '')) !== '' ? (string) $event->title : 'Atividade',
                    'details' => trim((string) ($event->description ?? '')) !== '' ? (string) $event->description : 'Sem descrição.',
                    'owner' => $event->owner?->name,
                    'occurred_at' => $timestamp?->format('d/m/Y H:i') ?? '-',
                    'sort_at' => $timestamp?->getTimestamp() ?? 0,
                ];
            })
            ->all();

        $emailItems = DealEmailLog::query()
            ->where('deal_id', $deal->id)
            ->with('sender:id,name')
            ->orderByDesc('sent_at')
            ->limit(100)
            ->get()
            ->map(function (DealEmailLog $emailLog): array {
                $isFollowUp = $emailLog->email_type === 'follow_up';

                return [
                    'key' => sprintf('deal-email-%d', $emailLog->id),
                    'entry_type' => 'email',
                    'activity_type' => $isFollowUp ? 'follow_up' : 'proposal',
                    'title' => $isFollowUp ? 'Follow up enviado por email' : 'Proposta enviada por email',
                    'details' => sprintf(
                        'Para: %s | Assunto: %s',
                        $emailLog->to_email,
                        $emailLog->subject
                    ),
                    'owner' => $emailLog->sender?->name,
                    'occurred_at' => $emailLog->sent_at?->format('d/m/Y H:i') ?? '-',
                    'sort_at' => $emailLog->sent_at?->getTimestamp() ?? 0,
                ];
            })
            ->all();

        $timeline = collect(array_merge($items, $activityItems, $emailItems))
            ->sortByDesc('sort_at')
            ->values()
            ->map(function (array $item): array {
                unset($item['sort_at']);

                return $item;
            })
            ->all();

        return $timeline;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function quickActivityTypes(): array
    {
        return [
            ['value' => 'call', 'label' => 'Chamada'],
            ['value' => 'task', 'label' => 'Tarefa'],
            ['value' => 'meeting', 'label' => 'Reunião'],
            ['value' => 'note', 'label' => 'Nota'],
        ];
    }

    private function quickActivityDuration(string $activityType): int
    {
        return match ($activityType) {
            'task' => 30,
            'meeting' => 60,
            'note' => 5,
            default => 20,
        };
    }

    private function quickActivityDefaultTitle(string $activityType, Deal $deal): string
    {
        $label = match ($activityType) {
            'call' => 'Chamada',
            'task' => 'Tarefa',
            'meeting' => 'Reunião',
            'note' => 'Nota',
            default => 'Atividade',
        };

        return sprintf('%s - %s', $label, $deal->title);
    }

    private function stageLabel(string $stage): string
    {
        return collect($this->stageOptions())
            ->firstWhere('value', $stage)['label'] ?? $stage;
    }

    private function syncFollowUpOnStageTransition(Deal $deal, ?string $fromStage, string $toStage): void
    {
        if ($toStage === Deal::STAGE_FOLLOW_UP && $fromStage !== Deal::STAGE_FOLLOW_UP) {
            $this->dealFollowUpService->start($deal);

            return;
        }

        if ($fromStage === Deal::STAGE_FOLLOW_UP && $toStage !== Deal::STAGE_FOLLOW_UP && $deal->follow_up_active) {
            $this->dealFollowUpService->stop($deal, DealFollowUpService::STOP_STAGE_CHANGED);
        }
    }

    /**
     * @return array{active: bool, next_send_at: string|null, last_sent_at: string|null, started_at: string|null, stop_reason: string|null, stop_reason_label: string|null, customer_replied_at: string|null}
     */
    private function followUpPayload(Deal $deal): array
    {
        return [
            'active' => (bool) $deal->follow_up_active,
            'next_send_at' => $deal->follow_up_next_send_at?->timezone('Europe/Lisbon')->format('d/m/Y H:i'),
            'last_sent_at' => $deal->follow_up_last_sent_at?->timezone('Europe/Lisbon')->format('d/m/Y H:i'),
            'started_at' => $deal->follow_up_started_at?->timezone('Europe/Lisbon')->format('d/m/Y H:i'),
            'stop_reason' => $deal->follow_up_stop_reason,
            'stop_reason_label' => $this->followUpStopReasonLabel($deal->follow_up_stop_reason),
            'customer_replied_at' => $deal->follow_up_customer_replied_at?->timezone('Europe/Lisbon')->format('d/m/Y H:i'),
        ];
    }

    private function followUpStopReasonLabel(?string $reason): ?string
    {
        if (! is_string($reason) || $reason === '') {
            return null;
        }

        return match ($reason) {
            DealFollowUpService::STOP_STAGE_CHANGED => 'Negócio saiu da etapa Follow Up',
            DealFollowUpService::STOP_CUSTOMER_REPLIED => 'Cliente respondeu',
            DealFollowUpService::STOP_MANUAL => 'Cancelado manualmente',
            DealFollowUpService::STOP_MISSING_RECIPIENT => 'Sem email de destino',
            default => $reason,
        };
    }

    /**
     * @return array{has_file: bool, file_name: string|null, mime_type: string|null, size_label: string|null, uploaded_at: string|null, uploaded_by: string|null, download_url: string|null}
     */
    private function proposalPayload(Deal $deal): array
    {
        $hasFile = is_string($deal->proposal_path) && $deal->proposal_path !== '';

        return [
            'has_file' => $hasFile,
            'file_name' => $hasFile ? $deal->proposal_original_name : null,
            'mime_type' => $hasFile ? $deal->proposal_mime_type : null,
            'size_label' => $hasFile ? $this->formatBytes((int) ($deal->proposal_size ?? 0)) : null,
            'uploaded_at' => $deal->proposal_uploaded_at?->format('d/m/Y H:i'),
            'uploaded_by' => $deal->proposalUploader?->name,
            'download_url' => $hasFile ? route('deals.proposal.download', $deal) : null,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return sprintf('%d B', $bytes);
        }

        if ($bytes < 1024 * 1024) {
            return sprintf('%.1f KB', $bytes / 1024);
        }

        return sprintf('%.2f MB', $bytes / (1024 * 1024));
    }

    /**
     * @return array{to_email: string, subject: string, body: string}
     */
    private function proposalEmailDefaults(Deal $deal): array
    {
        return [
            'to_email' => $deal->entity?->email ?? '',
            'subject' => $this->defaultProposalEmailSubject($deal),
            'body' => $this->defaultProposalEmailBody($deal),
        ];
    }

    private function defaultProposalEmailSubject(Deal $deal): string
    {
        return sprintf('Proposta comercial - %s', $deal->title);
    }

    private function defaultProposalEmailBody(Deal $deal): string
    {
        $entityName = $deal->entity?->name ?? 'cliente';

        return implode("\n", [
            sprintf('Ola %s,', $entityName),
            '',
            'Segue em anexo a proposta comercial para analise.',
            'Se precisares de algum ajuste ou tiveres duvidas, estou disponivel.',
            '',
            'Cumprimentos,',
            (string) (auth()->user()?->name ?? 'Equipa Comercial'),
        ]);
    }
}
