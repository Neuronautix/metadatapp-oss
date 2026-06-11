import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { ExternalLink, Book, Code, FileText } from 'lucide-react'
import { Link } from 'react-router-dom'

export function DocumentationPage() {
    return (
        <div className="space-y-6">
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Book className="h-5 w-5" />
                            User Guides
                        </CardTitle>
                        <CardDescription>Learn how to manage your lab.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <Link to="/investigations" className="block text-sm text-primary hover:underline">
                            Getting Started with Metadatapp
                        </Link>
                        <Link to="/connected-apps" className="block text-sm text-primary hover:underline">
                            Managing Connected Apps
                        </Link>
                        <Link to="/studies" className="block text-sm text-primary hover:underline">
                            Creating Studies
                        </Link>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Code className="h-5 w-5" />
                            API Reference
                        </CardTitle>
                        <CardDescription>Integrate with external tools.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <Link to="/settings/profile" className="block text-sm text-primary hover:underline">
                            Authentication
                        </Link>
                        <Link to="/metadata" className="block text-sm text-primary hover:underline">
                            Endpoints Overview
                        </Link>
                        <Link to="/connected-apps" className="block text-sm text-primary hover:underline">
                            Webhooks
                        </Link>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileText className="h-5 w-5" />
                            Release Notes
                        </CardTitle>
                        <CardDescription>What's new in Metadatapp.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium">v2.4.0</span>
                            <Badge variant="outline">Current</Badge>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Introduced new Dashboard and Connected Apps configuration.
                        </p>
                        <Link to="/audit" className="mt-2 block text-sm text-primary hover:underline">
                            View Changelog
                        </Link>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Need Help?</CardTitle>
                    <CardDescription>
                        Our support team is available 24/7 to assist you.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Button asChild>
                        <a href="mailto:support@metadatapp.lab">
                            Contact Support <ExternalLink className="ml-2 h-4 w-4" />
                        </a>
                    </Button>
                </CardContent>
            </Card>
        </div>
    )
}
