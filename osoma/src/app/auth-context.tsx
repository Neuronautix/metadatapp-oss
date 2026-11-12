import React, { createContext, useContext, useEffect, useState } from 'react';
import { AuthService, AuthSession } from '@/lib/auth.ts';

interface AuthContextValue {
  session: AuthSession | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: () => Promise<void>;
  logout: () => void;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [session, setSession] = useState<AuthSession | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const authService = AuthService.getInstance();

  useEffect(() => {
    // Initial load
    setSession(authService.getSession());
    setIsLoading(false);

    // Subscribe to session changes (login, logout, refresh)
    const unsubscribe = authService.subscribe((newSession) => {
      setSession(newSession);
    });

    return () => unsubscribe();
  }, []);

  const login = async () => {
    setIsLoading(true);
    try {
      await authService.login();
      // If we're still here (e.g. bypass/mock mode), reset loading
      setIsLoading(false);
    } catch (error) {
      console.error('Login failed:', error);
      setIsLoading(false);
      throw error;
    }
  };

  const logout = () => {
    authService.logout();
    setSession(null);
  };

  const value: AuthContextValue = {
    session,
    isAuthenticated: authService.isAuthenticated(),
    isLoading,
    login,
    logout,
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
