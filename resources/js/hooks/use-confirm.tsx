import { useCallback, useState } from 'react';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';

type ConfirmOptions = {
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'default' | 'destructive';
};

export function useConfirm() {
    const [promise, setPromise] = useState<{ resolve: (value: boolean) => void } | null>(null);
    const [options, setOptions] = useState<ConfirmOptions>({
        title: '',
        description: '',
    });

    const confirm = useCallback(
        (opts: ConfirmOptions) =>
            new Promise<boolean>((resolve) => {
                setOptions(opts);
                setPromise({ resolve });
            }),
        [],
    );

    const handleConfirm = useCallback(() => {
        promise?.resolve(true);
        setPromise(null);
    }, [promise]);

    const handleCancel = useCallback(() => {
        promise?.resolve(false);
        setPromise(null);
    }, [promise]);

    const ConfirmationDialog = () => (
        <ConfirmDialog
            open={promise !== null}
            onOpenChange={(open) => {
                if (!open) {
                    handleCancel();
                }
            }}
            title={options.title}
            description={options.description}
            confirmLabel={options.confirmLabel}
            cancelLabel={options.cancelLabel}
            variant={options.variant}
            onConfirm={handleConfirm}
        />
    );

    return { confirm, ConfirmationDialog };
}
