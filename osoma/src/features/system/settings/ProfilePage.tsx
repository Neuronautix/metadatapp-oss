import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Button } from '@/components/ui/button.tsx'
import {
    Form,
    FormControl,
    FormDescription,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Textarea } from '@/components/ui/textarea.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar.tsx'
import { useAuth } from '@/app/auth-context.tsx'

const profileFormSchema = z.object({
    username: z
        .string()
        .min(2, {
            message: 'Username must be at least 2 characters.',
        })
        .max(30, {
            message: 'Username must not be longer than 30 characters.',
        }),
    email: z
        .string({
            required_error: 'Please select an email to display.',
        })
        .email(),
    bio: z.string().max(160).min(4),
    urls: z
        .array(
            z.object({
                value: z.string().url({ message: 'Please enter a valid URL.' }),
            })
        )
        .optional(),
})

type ProfileFormValues = z.infer<typeof profileFormSchema>

// This can come from your mock API later
const defaultValues: Partial<ProfileFormValues> = {
    username: 'dr_science',
    email: 'researcher@metadatapp.lab',
    bio: 'Senior Principal Investigator focused on metadata resilience.',
}

export function ProfilePage() {
    const { toast } = useToast()
    const { session } = useAuth()
    const form = useForm<ProfileFormValues>({
        resolver: zodResolver(profileFormSchema),
        defaultValues,
        mode: 'onChange',
    })

    function onSubmit(data: ProfileFormValues) {
        toast({
            title: 'You submitted the following values:',
            description: JSON.stringify(data, null, 2),
        })
    }

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Account summary</CardTitle>
                    <CardDescription>Identity and access details from your active session.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 text-sm text-slate-600 md:grid-cols-3">
                    <div>
                        <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Name</p>
                        <p className="font-semibold text-slate-800">{session?.user?.name ?? 'Unknown'}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Email</p>
                        <p className="font-semibold text-slate-800">{session?.user?.email ?? 'Unknown'}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Subject ID</p>
                        <p className="font-semibold text-slate-800">{session?.user?.sub ?? 'Unknown'}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Organization</p>
                        <p className="font-semibold text-slate-800">
                            {session?.user?.accountName ?? session?.user?.accountKey ?? 'Unknown'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Organization role</p>
                        <p className="font-semibold text-slate-800">{session?.user?.organizationRole ?? 'Unknown'}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Profile</CardTitle>
                    <CardDescription>
                        This is how others will see you on the site.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form {...form}>
                        <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-8">
                            <div className="flex items-center gap-6">
                                <Avatar className="h-20 w-20">
                                    <AvatarImage src="/placeholder-avatar.jpg" alt="@shadcn" />
                                    <AvatarFallback>DR</AvatarFallback>
                                </Avatar>
                                <Button variant="outline" type="button" disabled>Change Avatar</Button>
                            </div>

                            <FormField
                                control={form.control}
                                name="username"
                                render={({ field }) => (
                                    <FormItem>
                                        <FormLabel>Username</FormLabel>
                                        <FormControl>
                                            <Input placeholder="shadcn" {...field} />
                                        </FormControl>
                                        <FormDescription>
                                            This is your public display name. It can be your real name or a pseudonym.
                                        </FormDescription>
                                        <FormMessage />
                                    </FormItem>
                                )}
                            />
                            <FormField
                                control={form.control}
                                name="email"
                                render={({ field }) => (
                                    <FormItem>
                                        <FormLabel>Email</FormLabel>
                                        <FormControl>
                                            <Input placeholder="Select a verified email to display" {...field} />
                                        </FormControl>
                                        <FormDescription>
                                            You can manage verified email addresses in your identity provider profile.
                                        </FormDescription>
                                        <FormMessage />
                                    </FormItem>
                                )}
                            />
                            <FormField
                                control={form.control}
                                name="bio"
                                render={({ field }) => (
                                    <FormItem>
                                        <FormLabel>Bio</FormLabel>
                                        <FormControl>
                                            <Textarea
                                                placeholder="Tell us a little bit about yourself"
                                                className="resize-none"
                                                {...field}
                                            />
                                        </FormControl>
                                        <FormDescription>
                                            You can <span>@mention</span> other users and organizations to link to them.
                                        </FormDescription>
                                        <FormMessage />
                                    </FormItem>
                                )}
                            />
                            <Button type="submit">Update profile</Button>
                        </form>
                    </Form>
                </CardContent>
            </Card>
        </div>
    )
}
