import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { ExternalLink, Book, Code, Plug, LifeBuoy } from 'lucide-react'
import { docsUrl } from '@/lib/docs.ts'

type DocLink = {
    label: string
    path: string
}

type DocSection = {
    title: string
    description: string
    icon: typeof Book
    links: DocLink[]
}

const sections: DocSection[] = [
    {
        title: 'User Guides',
        description: 'Learn how to use Metadatapp.',
        icon: Book,
        links: [
            { label: 'Getting started', path: 'getting-started.html' },
            { label: 'Using Osoma (full tour)', path: 'using-osoma/index.html' },
            { label: 'Importing & curating data', path: 'using-osoma/importing-data.html' },
            { label: 'FAIR & exports', path: 'using-osoma/fair.html' },
        ],
    },
    {
        title: 'Connecting apps',
        description: 'Integrate external lab systems.',
        icon: Plug,
        links: [
            { label: 'All integrations', path: 'connecting-apps/index.html' },
            { label: 'eLabFTW', path: 'connecting-apps/elabftw.html' },
            { label: 'SoftMouse', path: 'connecting-apps/softmouse.html' },
            { label: 'Tecniplast DVC', path: 'connecting-apps/tecniplast.html' },
        ],
    },
    {
        title: 'API & developers',
        description: 'Integrate and extend.',
        icon: Code,
        links: [
            { label: 'API reference', path: 'for-developers/api-reference.html' },
            { label: 'Architecture & extending', path: 'for-developers/index.html' },
            { label: 'Self-hosting & configuration', path: 'self-hosting/index.html' },
        ],
    },
]

export function DocumentationPage() {
    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Documentation</CardTitle>
                    <CardDescription>
                        The complete User &amp; Integration Guide opens in a new tab.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Button asChild>
                        <a href={docsUrl()} target="_blank" rel="noreferrer">
                            Open the documentation <ExternalLink className="ml-2 h-4 w-4" />
                        </a>
                    </Button>
                </CardContent>
            </Card>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {sections.map((section) => (
                    <Card key={section.title}>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <section.icon className="h-5 w-5" />
                                {section.title}
                            </CardTitle>
                            <CardDescription>{section.description}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {section.links.map((link) => (
                                <a
                                    key={link.path}
                                    href={docsUrl(link.path)}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="block text-sm text-primary hover:underline"
                                >
                                    {link.label}
                                </a>
                            ))}
                        </CardContent>
                    </Card>
                ))}
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <LifeBuoy className="h-5 w-5" />
                        Need help?
                    </CardTitle>
                    <CardDescription>
                        Check the FAQ &amp; troubleshooting, or reach out through the project's support channels.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-x-2">
                    <Button asChild variant="outline">
                        <a href={docsUrl('reference/faq.html')} target="_blank" rel="noreferrer">
                            FAQ &amp; troubleshooting <ExternalLink className="ml-2 h-4 w-4" />
                        </a>
                    </Button>
                    <Button asChild variant="outline">
                        <a
                            href="https://github.com/Neuronautix/metadatapp-oss/blob/main/SUPPORT.md"
                            target="_blank"
                            rel="noreferrer"
                        >
                            Support <ExternalLink className="ml-2 h-4 w-4" />
                        </a>
                    </Button>
                </CardContent>
            </Card>
        </div>
    )
}
