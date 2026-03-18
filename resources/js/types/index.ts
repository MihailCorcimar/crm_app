export * from './auth';
export * from './navigation';
export * from './ui';

import type { Auth } from './auth';

export type TenantContextItem = {
    id: number;
    name: string;
    slug: string;
    brand_primary_color: string;
};

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    auth: Auth;
    company: {
        name: string;
        logo_url: string | null;
    };
    tenant: {
        active: TenantContextItem | null;
        available: TenantContextItem[];
    };
    automation_notifications: {
        unread_count: number;
        categories: Array<{
            key: string;
            label: string;
            unread_count: number;
            items: Array<{
                id: string;
                source_id: number | null;
                title: string;
                message: string | null;
                href: string | null;
                action_label: string | null;
                read_at: string | null;
                created_at: string | null;
                can_mark_read: boolean;
            }>;
        }>;
    };
    sidebarOpen: boolean;
    [key: string]: unknown;
};
