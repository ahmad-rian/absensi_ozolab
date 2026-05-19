import { Inbox } from 'lucide-react';
import { Button } from '@/components/ui/button';

type EmptyStateProps = {
    icon?: React.ReactNode;
    title: string;
    description?: string;
    action?: {
        label: string;
        onClick: () => void;
    };
};

export function EmptyState({ icon, title, description, action }: EmptyStateProps) {
    return (
        <div className="flex flex-col items-center justify-center py-12 text-center">
            <div className="text-muted-foreground mb-4">{icon ?? <Inbox className="size-12" />}</div>
            <h3 className="text-lg font-semibold">{title}</h3>
            {description && <p className="text-muted-foreground mt-1 max-w-sm text-sm">{description}</p>}
            {action && (
                <Button className="mt-4" onClick={action.onClick}>
                    {action.label}
                </Button>
            )}
        </div>
    );
}
