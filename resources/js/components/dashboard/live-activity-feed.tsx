import { LogIn, LogOut } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

type ActivityItem = {
    id: number;
    message: string;
    time: string;
    type: string;
    status: string;
    initials: string;
};

type LiveActivityFeedProps = {
    data?: ActivityItem[];
};

export function LiveActivityFeed({ data }: LiveActivityFeedProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Aktivitas Terkini</CardTitle>
                <CardDescription>Feed real-time</CardDescription>
            </CardHeader>
            <CardContent>
                {!data ? (
                    <div className="space-y-3">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <Skeleton key={i} className="h-10 w-full" />
                        ))}
                    </div>
                ) : (
                    <div className="relative max-h-[500px] space-y-0 overflow-y-auto">
                        {/* Timeline line */}
                        <div className="border-border absolute top-0 bottom-0 left-[19px] w-px border-l" />

                        {data.map((item) => (
                            <div key={item.id} className="relative flex items-start gap-3 py-2.5">
                                <Avatar className="relative z-10 size-9 shrink-0">
                                    <AvatarFallback className="bg-card border text-xs">{item.initials}</AvatarFallback>
                                </Avatar>
                                <div className="flex-1 space-y-0.5">
                                    <p className="text-sm leading-snug">{item.message}</p>
                                    <p className="text-muted-foreground flex items-center gap-1 text-xs">
                                        {item.type === 'CHECK_IN' ? <LogIn className="size-3" /> : <LogOut className="size-3" />}
                                        {item.time}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
