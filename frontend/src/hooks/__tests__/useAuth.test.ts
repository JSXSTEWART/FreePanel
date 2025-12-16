import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook } from '@testing-library/react';
import { Provider } from 'react-redux';
import { BrowserRouter } from 'react-router-dom';
import { configureStore } from '@reduxjs/toolkit';
import React from 'react';

// Mock the auth slice and actions
vi.mock('../store/authSlice', () => ({
  logout: vi.fn(() => ({ type: 'auth/logout' })),
  fetchUser: vi.fn(() => ({ type: 'auth/fetchUser' })),
}));

// Create a mock auth reducer for testing
const createMockStore = (initialState = {}) => {
  const defaultAuthState = {
    user: null,
    isAuthenticated: false,
    isLoading: false,
    error: null,
    requires2FA: false,
    tempToken: null,
    ...initialState,
  };

  return configureStore({
    reducer: {
      auth: (state = defaultAuthState) => state,
    },
  });
};

// Create wrapper component
const createWrapper = (store: ReturnType<typeof createMockStore>) => {
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(
      Provider,
      { store },
      React.createElement(BrowserRouter, null, children)
    );
  };
};

describe('useAuth hook', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
  });

  it('returns default unauthenticated state', async () => {
    const store = createMockStore();

    // Dynamic import to avoid hoisting issues
    const { useAuth } = await import('../useAuth');

    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper(store),
    });

    expect(result.current.user).toBeNull();
    expect(result.current.isAuthenticated).toBe(false);
    expect(result.current.isLoading).toBe(false);
    expect(result.current.isAdmin).toBe(false);
    expect(result.current.isReseller).toBe(false);
  });

  it('returns authenticated state with user', async () => {
    const mockUser = {
      id: 1,
      username: 'testuser',
      email: 'test@example.com',
      role: 'user',
    };

    const store = createMockStore({
      user: mockUser,
      isAuthenticated: true,
    });

    const { useAuth } = await import('../useAuth');

    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper(store),
    });

    expect(result.current.user).toEqual(mockUser);
    expect(result.current.isAuthenticated).toBe(true);
    expect(result.current.isAdmin).toBe(false);
    expect(result.current.isReseller).toBe(false);
  });

  it('correctly identifies admin user', async () => {
    const adminUser = {
      id: 1,
      username: 'admin',
      email: 'admin@example.com',
      role: 'admin',
    };

    const store = createMockStore({
      user: adminUser,
      isAuthenticated: true,
    });

    const { useAuth } = await import('../useAuth');

    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper(store),
    });

    expect(result.current.isAdmin).toBe(true);
    expect(result.current.isReseller).toBe(false);
  });

  it('correctly identifies reseller user', async () => {
    const resellerUser = {
      id: 1,
      username: 'reseller',
      email: 'reseller@example.com',
      role: 'reseller',
    };

    const store = createMockStore({
      user: resellerUser,
      isAuthenticated: true,
    });

    const { useAuth } = await import('../useAuth');

    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper(store),
    });

    expect(result.current.isAdmin).toBe(false);
    expect(result.current.isReseller).toBe(true);
  });

  it('returns loading state correctly', async () => {
    const store = createMockStore({
      isLoading: true,
    });

    const { useAuth } = await import('../useAuth');

    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper(store),
    });

    expect(result.current.isLoading).toBe(true);
  });

  it('returns error state correctly', async () => {
    const store = createMockStore({
      error: 'Authentication failed',
    });

    const { useAuth } = await import('../useAuth');

    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper(store),
    });

    expect(result.current.error).toBe('Authentication failed');
  });

  it('returns 2FA state correctly', async () => {
    const store = createMockStore({
      requires2FA: true,
      tempToken: 'temp_token_123',
    });

    const { useAuth } = await import('../useAuth');

    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper(store),
    });

    expect(result.current.requires2FA).toBe(true);
    expect(result.current.tempToken).toBe('temp_token_123');
  });

  it('provides logout function', async () => {
    const store = createMockStore({
      user: { id: 1, username: 'test', role: 'user' },
      isAuthenticated: true,
    });

    const { useAuth } = await import('../useAuth');

    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper(store),
    });

    expect(typeof result.current.logout).toBe('function');
  });
});
