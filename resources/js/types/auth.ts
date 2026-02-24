export type User = {
    id: number;
    name: string;
    email: string;
    mobile?: string | null;
    permission_group_id?: number | null;
    current_tenant_id?: number | null;
    status?: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
