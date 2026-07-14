import { useState, useCallback } from 'react';

const API_BASE = import.meta.env.VITE_API_URL || '/api';

export function useApi<T>(endpoint: string, method: 'GET' | 'POST' | 'PUT' | 'DELETE' = 'GET') {
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const execute = useCallback(async (body?: unknown, queryParams?: Record<string, string>) => {
    setLoading(true);
    setError(null);
    try {
      let url = `${API_BASE}/${endpoint}`;
      if (queryParams) url += `?${new URLSearchParams(queryParams).toString()}`;
      
      const options: RequestInit = {
        method,
        headers: { 'Content-Type': 'application/json' },
      };
      const token = localStorage.getItem('admin_token');
      if (token) options.headers = { ...options.headers, Authorization: `Bearer ${token}` };
      if (body && method !== 'GET') options.body = JSON.stringify(body);
      
      const response = await fetch(url, options);
      const result = await response.json();
      if (!response.ok || result.error) throw new Error(result.message || `HTTP ${response.status}`);
      
      setData(result);
      return result as Record<string, unknown>;
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Error';
      setError(msg);
      return null;
    } finally {
      setLoading(false);
    }
  }, [endpoint, method]);

  const reset = useCallback(() => { setData(null); setError(null); }, []);
  return { data, loading, error, execute, reset };
}

export async function apiRequest(
  endpoint: string,
  method: 'GET' | 'POST' | 'PUT' | 'DELETE' = 'GET',
  body?: unknown,
  queryParams?: Record<string, string>
): Promise<Record<string, unknown>> {
  let url = `${API_BASE}/${endpoint}`;
  if (queryParams) url += `?${new URLSearchParams(queryParams).toString()}`;
  
  const options: RequestInit = {
    method,
    headers: { 'Content-Type': 'application/json' },
  };
  const token = localStorage.getItem('admin_token');
  if (token) options.headers = { ...options.headers, Authorization: `Bearer ${token}` };
  if (body && method !== 'GET') options.body = JSON.stringify(body);
  
  const response = await fetch(url, options);
  const data = await response.json();
  if (!response.ok || data.error) throw new Error(data.message || `HTTP ${response.status}`);
  return data as Record<string, unknown>;
}

export function useAuth() {
  const [isAuthenticated, setIsAuthenticated] = useState(() => !!localStorage.getItem('admin_token'));
  const login = useCallback((token: string) => { localStorage.setItem('admin_token', token); setIsAuthenticated(true); }, []);
  const logout = useCallback(() => { localStorage.removeItem('admin_token'); setIsAuthenticated(false); }, []);
  return { isAuthenticated, login, logout };
}
