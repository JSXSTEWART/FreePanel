import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { Provider } from 'react-redux';
import { BrowserRouter } from 'react-router-dom';
import { configureStore } from '@reduxjs/toolkit';
import { useAuth } from '../useAuth';
import authReducer from '../../store/authSlice';

// Mock navigate
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

const createMockStore = (initialState = {}) => {
  return configureStore({
    reducer: {
      auth: authReducer,
    },
    preloadedState: {
      auth: {
        user: null,
        isAuthenticated: false,
        isLoading: false,
        error: null,
        requires2FA: false,
        tempToken: null,
        ...initialState,
      },
    },
  });
};

const wrapper = (store: ReturnType<typeof createMockStore>) =>
  function Wrapper({ children }: { children: React.ReactNode }) {
    return (
      <Provider store={store}>
        <BrowserRouter>{children}</BrowserRouter>
      </Provider>
    );
  };

describe('useAuth Hook', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
  });

  it('returns initial unauthenticated state', () => {
    const store = createMockStore();
    const { result } = renderHook(() => useAuth(), { wrapper: wrapper(store) });

    expect(result.current.user).toBeNull();
    expect(result.current.isAuthenticated).toBe(false);
    expect(result.current.isLoading).toBe(false);
    expect(result.current.error).toBeNull();
  });

  it('returns authenticated user when logged in', () => {
    const mockUser = {
      id: 1,
      email: 'admin@example.com',
      role: 'admin',
      name: 'Admin User',
    };

    const store = createMockStore({
      user: mockUser,
      isAuthenticated: true,
    });

    const { result } = renderHook(() => useAuth(), { wrapper: wrapper(store) });

    expect(result.current.user).toEqual(mockUser);
    expect(result.current.isAuthenticated).toBe(true);
    expect(result.current.isAdmin).toBe(true);
  });

  it('identifies reseller role correctly', () => {
    const mockUser = {
      id: 2,
      email: 'reseller@example.com',
      role: 'reseller',
      name: 'Reseller User',
    };

    const store = createMockStore({
      user: mockUser,
      isAuthenticated: true,
    });

    const { result } = renderHook(() => useAuth(), { wrapper: wrapper(store) });

    expect(result.current.isReseller).toBe(true);
    expect(result.current.isAdmin).toBe(false);
  });

  it('navigates to login on logout', async () => {
    const store = createMockStore({
      user: { id: 1, email: 'test@example.com', role: 'user', name: 'Test' },
      isAuthenticated: true,
    });

    const { result } = renderHook(() => useAuth(), { wrapper: wrapper(store) });

    await result.current.logout();

    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith('/login');
    });
  });

  it('returns 2FA state when required', () => {
    const store = createMockStore({
      requires2FA: true,
      tempToken: 'temp-token-123',
    });

    const { result } = renderHook(() => useAuth(), { wrapper: wrapper(store) });

    expect(result.current.requires2FA).toBe(true);
    expect(result.current.tempToken).toBe('temp-token-123');
  });

  it('returns error state when present', () => {
    const mockError = 'Authentication failed';
    const store = createMockStore({
      error: mockError,
    });

    const { result } = renderHook(() => useAuth(), { wrapper: wrapper(store) });

    expect(result.current.error).toBe(mockError);
  });
});
