import { computed, inject } from 'vue';
import { useFieldError, useIsFieldDirty, useIsFieldTouched, useIsFieldValid } from 'vee-validate';
import { FORM_FIELD_INJECTION_KEY, FORM_ITEM_INJECTION_KEY } from './injectionKeys';

export function useFormField() {
    const fieldContext = inject(FORM_FIELD_INJECTION_KEY);
    const itemContext = inject(FORM_ITEM_INJECTION_KEY);

    if (!fieldContext || !itemContext) {
        throw new Error('useFormField should be used within <FormField> and <FormItem>.');
    }

    const fieldError = useFieldError(() => fieldContext.name.value);
    const isDirty = useIsFieldDirty(() => fieldContext.name.value);
    const isTouched = useIsFieldTouched(() => fieldContext.name.value);
    const isValid = useIsFieldValid(() => fieldContext.name.value);

    const formItemId = computed(() => itemContext.id.value);

    return {
        name: computed(() => fieldContext.name.value),
        formItemId,
        formDescriptionId: computed(() => `${formItemId.value}-description`),
        formMessageId: computed(() => `${formItemId.value}-message`),
        error: computed(() => fieldError.value),
        isDirty,
        isTouched,
        isValid,
    };
}
