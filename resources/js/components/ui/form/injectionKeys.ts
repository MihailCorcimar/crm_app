import type { InjectionKey, Ref } from 'vue';

export const FORM_ITEM_INJECTION_KEY = Symbol() as InjectionKey<{ id: Ref<string> }>;

export const FORM_FIELD_INJECTION_KEY = Symbol() as InjectionKey<{ name: Ref<string> }>;
