import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

type Column<T> = {
    key: string;
    header: string;
    render?: (row: T) => React.ReactNode;
};

type DataTableProps<T> = {
    columns: Column<T>[];
    data: T[];
    emptyMessage?: string;
};

export function DataTable<T extends Record<string, unknown>>({ columns, data, emptyMessage = 'Tidak ada data' }: DataTableProps<T>) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    {columns.map((col) => (
                        <TableHead key={col.key}>{col.header}</TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>
                {data.length === 0 ? (
                    <TableRow>
                        <TableCell colSpan={columns.length} className="text-muted-foreground py-8 text-center">
                            {emptyMessage}
                        </TableCell>
                    </TableRow>
                ) : (
                    data.map((row, i) => (
                        <TableRow key={i}>
                            {columns.map((col) => (
                                <TableCell key={col.key}>{col.render ? col.render(row) : String(row[col.key] ?? '')}</TableCell>
                            ))}
                        </TableRow>
                    ))
                )}
            </TableBody>
        </Table>
    );
}
