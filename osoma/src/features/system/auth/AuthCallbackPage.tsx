import { useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { AuthService } from '@/lib/auth.ts';
import { useToast } from '@/components/ui/toast.tsx';

export function AuthCallbackPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const { toast } = useToast();

  useEffect(() => {
    const handleCallback = async () => {
      const code = searchParams.get('code');
      const state = searchParams.get('state');
      const error = searchParams.get('error');

      if (error) {
        console.error('OAuth error:', error);
        toast({
          title: 'Authentication failed',
          description: `Error: ${error}`,
          variant: 'error',
        });
        navigate('/');
        return;
      }

      if (!code || !state) {
        console.error('Missing code or state parameter');
        toast({
          title: 'Authentication failed',
          description: 'Missing authorization code',
          variant: 'error',
        });
        navigate('/');
        return;
      }

      try {
        const authService = AuthService.getInstance();
        await authService.handleCallback(code, state);
        toast({
          title: 'Authentication successful',
          description: 'You are now logged in',
        });
        navigate('/');
      } catch (error) {
        console.error('Callback handling failed:', error);
        toast({
          title: 'Authentication failed',
          description: 'Failed to complete login process',
          variant: 'error',
        });
        navigate('/');
      }
    };

    handleCallback();
  }, [searchParams, navigate, toast]);

  return (
    <div className="flex min-h-screen items-center justify-center">
      <div className="text-center">
        <div className="mb-4 h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent mx-auto"></div>
        <p className="text-lg">Completing authentication...</p>
      </div>
    </div>
  );
}
