<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { AppPageProps } from '@/types';

type NotificationItem = {
    id: string;
    source_id: number | null;
    title: string;
    message: string | null;
    href: string | null;
    action_label: string | null;
    read_at: string | null;
    created_at: string | null;
    can_mark_read: boolean;
};

type NotificationCategory = {
    key: string;
    label: string;
    unread_count: number;
    items: NotificationItem[];
};

type NotificationItemWithCategory = NotificationItem & {
    category_key: string;
    category_label: string;
};

type NotificationsPayload = {
    unread_count: number;
    categories: NotificationCategory[];
};

const page = usePage<AppPageProps>();
const selectedCategory = ref<string>('all');

const notificationsPayload = computed<NotificationsPayload>(() => {
    const payload = page.props.automation_notifications as NotificationsPayload | undefined;
    return payload ?? { unread_count: 0, categories: [] };
});

const unreadCount = computed<number>(() => Math.max(0, notificationsPayload.value.unread_count ?? 0));
const categories = computed<NotificationCategory[]>(() => notificationsPayload.value.categories ?? []);

const allItems = computed<NotificationItemWithCategory[]>(() => {
    return categories.value.flatMap((category) =>
        category.items.map((item) => ({
            ...item,
            category_key: category.key,
            category_label: category.label,
        })),
    );
});

const visibleItems = computed<NotificationItemWithCategory[]>(() => {
    if (selectedCategory.value === 'all') {
        return allItems.value;
    }

    return allItems.value.filter((item) => item.category_key === selectedCategory.value);
});

const automationCategory = computed<NotificationCategory | null>(() => {
    return categories.value.find((category) => category.key === 'automations') ?? null;
});

const unreadBadgeText = computed<string>(() => {
    if (unreadCount.value > 99) {
        return '99+';
    }

    return String(unreadCount.value);
});

function markRead(notificationId: number): void {
    router.patch(`/automations/notifications/${notificationId}/read`, {}, { preserveScroll: true, preserveState: true });
}

function markAllRead(): void {
    router.patch('/automations/notifications/read-all', {}, { preserveScroll: true, preserveState: true });
}

function isCategoryActive(key: string): boolean {
    return selectedCategory.value === key;
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="icon" class="relative h-9 w-9 rounded-full">
                <Bell class="h-4 w-4" />
                <span
                    v-if="unreadCount > 0"
                    class="absolute -top-1 -right-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold leading-none text-white"
                >
                    {{ unreadBadgeText }}
                </span>
                <span class="sr-only">Notificacoes</span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-[380px] max-w-[90vw] rounded-xl p-0">
            <div class="flex items-center justify-between px-3 py-2">
                <DropdownMenuLabel class="p-0 text-sm font-semibold">Notificacoes</DropdownMenuLabel>
                <button
                    v-if="(automationCategory?.unread_count ?? 0) > 0"
                    type="button"
                    class="text-xs text-muted-foreground hover:text-foreground"
                    @click="markAllRead"
                >
                    Marcar automacoes
                </button>
            </div>

            <DropdownMenuSeparator />

            <div class="flex flex-wrap gap-2 px-3 py-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs"
                    :class="isCategoryActive('all') ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                    @click="selectedCategory = 'all'"
                >
                    Todas
                    <span class="rounded-full bg-black/15 px-1.5 py-0.5 text-[10px] leading-none">
                        {{ unreadCount }}
                    </span>
                </button>

                <button
                    v-for="category in categories"
                    :key="category.key"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs"
                    :class="isCategoryActive(category.key) ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                    @click="selectedCategory = category.key"
                >
                    {{ category.label }}
                    <span class="rounded-full bg-black/15 px-1.5 py-0.5 text-[10px] leading-none">
                        {{ category.unread_count }}
                    </span>
                </button>
            </div>

            <DropdownMenuSeparator />

            <div class="max-h-96 overflow-y-auto p-2">
                <div v-if="visibleItems.length === 0" class="rounded-md border border-dashed px-3 py-6 text-center text-sm text-muted-foreground">
                    Sem notificacoes.
                </div>

                <div v-else class="space-y-2">
                    <div
                        v-for="notification in visibleItems"
                        :key="notification.id"
                        class="rounded-lg border p-3 text-sm"
                        :class="notification.read_at ? 'bg-background' : 'bg-emerald-50/60 dark:bg-emerald-900/15'"
                    >
                        <div class="mb-1 flex items-start justify-between gap-2">
                            <div>
                                <p class="font-medium leading-5">{{ notification.title }}</p>
                                <p class="text-[11px] text-muted-foreground">{{ notification.category_label }}</p>
                            </div>
                            <span class="text-[11px] text-muted-foreground">{{ notification.created_at || '-' }}</span>
                        </div>

                        <p class="text-xs text-muted-foreground">{{ notification.message || '-' }}</p>

                        <div class="mt-2 flex items-center gap-2">
                            <Link
                                v-if="notification.href"
                                :href="notification.href"
                                class="rounded-md border px-2 py-1 text-xs hover:bg-muted"
                            >
                                {{ notification.action_label || 'Abrir' }}
                            </Link>

                            <button
                                v-if="notification.can_mark_read && notification.read_at === null && notification.source_id !== null"
                                type="button"
                                class="rounded-md border px-2 py-1 text-xs hover:bg-muted"
                                @click="markRead(notification.source_id)"
                            >
                                Marcar lida
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <DropdownMenuSeparator />

            <div class="p-2">
                <div class="grid grid-cols-2 gap-2">
                    <Link href="/automations/deal-rules" class="rounded-md border px-2 py-1.5 text-center text-xs hover:bg-muted">
                        Ver automacoes
                    </Link>
                    <Link href="/dashboard" class="rounded-md border px-2 py-1.5 text-center text-xs hover:bg-muted">
                        Abrir dashboard
                    </Link>
                </div>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
