import { describe, it, expect, vi, beforeEach } from "vitest";
import { renderHook } from "@testing-library/react";
import { Provider } from "react-redux";
import { BrowserRouter } from "react-router-dom";
import { configureStore } from "@reduxjs/toolkit";
import { useAuth } from "../useAuth";
import authReducer from "../../store/authSlice";
import type { ReactNode } from "react";

// Mock localStorage
const localStorageMock = {
  getItem: vi.fn(),
  setItem: vi.fn(),
  removeItem: vi.fn(),
  clear: vi.fn(),
};
Object.defineProperty(window, "localStorage", {
  value: localStorageMock,
});

// Mock navigate
const mockNavigate = vi.fn();
vi.mock("react-router-dom", async () => {
  const actual = await vi.importActual("react-router-dom");
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

interface WrapperProps {
  children: ReactNode;
}

const createWrapper = (preloadedState = {}) => {
  const store = configureStore({
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
        ...preloadedState,
      },
    },
  });

  return function Wrapper({ children }: WrapperProps) {
    return (
      <Provider store={store}>
        <BrowserRouter>{children}</BrowserRouter>
      </Provider>
    );
  };
};

describe("useAuth", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorageMock.getItem.mockReturnValue(null);
  });

  it("returns initial unauthenticated state", () => {
    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper(),
    });

    expect(result.current.user).toBeNull();
    expect(result.current.isAuthenticated).toBe(false);
    expect(result.current.isLoading).toBe(false);
    expect(result.current.isAdmin).toBe(false);
    expect(result.current.isReseller).toBe(false);
  });

  it("returns authenticated state with user", () => {
    const mockUser = {
      id: 1,
      uuid: "test-uuid",
      username: "testuser",
      email: "test@example.com",
      role: "user" as const,
    };

    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper({ user: mockUser, isAuthenticated: true }),
    });

    expect(result.current.user).toEqual(mockUser);
    expect(result.current.isAuthenticated).toBe(true);
    expect(result.current.isAdmin).toBe(false);
    expect(result.current.isReseller).toBe(false);
  });

  it("identifies admin users correctly", () => {
    const adminUser = {
      id: 1,
      uuid: "admin-uuid",
      username: "admin",
      email: "admin@example.com",
      role: "admin" as const,
    };

    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper({ user: adminUser, isAuthenticated: true }),
    });

    expect(result.current.isAdmin).toBe(true);
    expect(result.current.isReseller).toBe(false);
  });

  it("identifies reseller users correctly", () => {
    const resellerUser = {
      id: 1,
      uuid: "reseller-uuid",
      username: "reseller",
      email: "reseller@example.com",
      role: "reseller" as const,
    };

    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper({ user: resellerUser, isAuthenticated: true }),
    });

    expect(result.current.isAdmin).toBe(false);
    expect(result.current.isReseller).toBe(true);
  });

  it("returns 2FA state when required", () => {
    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper({
        requires2FA: true,
        tempToken: "temp-token-123",
      }),
    });

    expect(result.current.requires2FA).toBe(true);
    expect(result.current.tempToken).toBe("temp-token-123");
  });

  it("returns error state", () => {
    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper({ error: "Invalid credentials" }),
    });

    expect(result.current.error).toBe("Invalid credentials");
  });

  it("returns loading state", () => {
    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper({ isLoading: true }),
    });

    expect(result.current.isLoading).toBe(true);
  });
});
