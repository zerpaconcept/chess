import ChessComImportController from '@/actions/App/Http/Controllers/Import/ChessComImportController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { create } from '@/routes/import/chess-com';
import { Head, useForm } from '@inertiajs/react';
import { Download } from 'lucide-react';

type TimeClassOption = {
    value: string;
    label: string;
};

type FormData = {
    username: string;
    from_date: string;
    to_date: string;
    time_classes: string[];
    color: 'white' | 'black' | 'both';
};

function defaultFromDate(): string {
    const date = new Date();
    date.setMonth(date.getMonth() - 1);

    return date.toISOString().split('T')[0];
}

function defaultToDate(): string {
    return new Date().toISOString().split('T')[0];
}

export default function ChessComImport({
    timeClassOptions,
}: {
    timeClassOptions: TimeClassOption[];
}) {
    const { data, setData, post, processing, errors } = useForm<FormData>({
        username: '',
        from_date: defaultFromDate(),
        to_date: defaultToDate(),
        time_classes: timeClassOptions.map((option) => option.value),
        color: 'both',
    });

    function toggleTimeClass(value: string): void {
        if (data.time_classes.includes(value)) {
            setData(
                'time_classes',
                data.time_classes.filter((timeClass) => timeClass !== value),
            );
        } else {
            setData('time_classes', [...data.time_classes, value]);
        }
    }

    function submit(e: React.FormEvent): void {
        e.preventDefault();
        post(ChessComImportController.store.url());
    }

    return (
        <>
            <Head title="Import from Chess.com" />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
                <Heading
                    title="Import from Chess.com"
                    description="Download your games from Chess.com by username, date range, time control, and color."
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Download className="size-5" />
                            Chess.com import
                        </CardTitle>
                        <CardDescription>
                            Games are fetched from the public Chess.com API and
                            saved to your library. Already-imported games are
                            skipped.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="username">Chess.com username</Label>
                                <Input
                                    id="username"
                                    name="username"
                                    value={data.username}
                                    onChange={(e) =>
                                        setData('username', e.target.value)
                                    }
                                    placeholder="hikaru"
                                    autoComplete="off"
                                    required
                                />
                                <InputError message={errors.username} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="from_date">From</Label>
                                    <Input
                                        id="from_date"
                                        name="from_date"
                                        type="date"
                                        value={data.from_date}
                                        onChange={(e) =>
                                            setData('from_date', e.target.value)
                                        }
                                        required
                                    />
                                    <InputError message={errors.from_date} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="to_date">To</Label>
                                    <Input
                                        id="to_date"
                                        name="to_date"
                                        type="date"
                                        value={data.to_date}
                                        onChange={(e) =>
                                            setData('to_date', e.target.value)
                                        }
                                        required
                                    />
                                    <InputError message={errors.to_date} />
                                </div>
                            </div>

                            <div className="grid gap-3">
                                <Label>Time controls</Label>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {timeClassOptions.map((option) => (
                                        <label
                                            key={option.value}
                                            htmlFor={`time-class-${option.value}`}
                                            className="flex items-center gap-3 rounded-lg border p-3 has-checked:border-primary has-checked:bg-primary/5"
                                        >
                                            <Checkbox
                                                id={`time-class-${option.value}`}
                                                checked={data.time_classes.includes(
                                                    option.value,
                                                )}
                                                onCheckedChange={() =>
                                                    toggleTimeClass(option.value)
                                                }
                                            />
                                            <span className="text-sm font-medium">
                                                {option.label}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                                <InputError message={errors.time_classes} />
                            </div>

                            <div className="grid gap-3">
                                <Label>Games as</Label>
                                <ToggleGroup
                                    type="single"
                                    variant="outline"
                                    value={data.color}
                                    onValueChange={(value) => {
                                        if (value) {
                                            setData(
                                                'color',
                                                value as FormData['color'],
                                            );
                                        }
                                    }}
                                    className="justify-start"
                                >
                                    <ToggleGroupItem value="both">
                                        Both colors
                                    </ToggleGroupItem>
                                    <ToggleGroupItem value="white">
                                        White only
                                    </ToggleGroupItem>
                                    <ToggleGroupItem value="black">
                                        Black only
                                    </ToggleGroupItem>
                                </ToggleGroup>
                                <InputError message={errors.color} />
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full sm:w-auto"
                            >
                                {processing ? 'Importing...' : 'Import games'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ChessComImport.layout = {
    breadcrumbs: [
        {
            title: 'Import from Chess.com',
            href: create(),
        },
    ],
};
