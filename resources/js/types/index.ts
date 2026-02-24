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
    sidebarOpen: boolean;
    [key: string]: unknown;
};
