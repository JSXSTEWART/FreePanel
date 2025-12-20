import { clsx } from "clsx";

interface BadgeProps {
  children: React.ReactNode;
  variant?: "default" | "primary" | "success" | "warning" | "danger" | "info";
  size?: "sm" | "md" | "lg";
  dot?: boolean;
  className?: string;
}

const variantClasses = {
  default: "bg-gray-100 text-gray-800",
  primary: "bg-primary-100 text-primary-800",
  success: "bg-green-100 text-green-800",
  warning: "bg-yellow-100 text-yellow-800",
  danger: "bg-red-100 text-red-800",
  info: "bg-blue-100 text-blue-800",
};

const dotColors = {
  default: "bg-gray-500",
  primary: "bg-primary-500",
  success: "bg-green-500",
  warning: "bg-yellow-500",
  danger: "bg-red-500",
  info: "bg-blue-500",
};

const sizeClasses = {
  sm: "px-2 py-0.5 text-xs",
  md: "px-2.5 py-1 text-xs",
  lg: "px-3 py-1.5 text-sm",
};

export default function Badge({
  children,
  variant = "default",
  size = "md",
  dot = false,
  className,
}: BadgeProps) {
  return (
    <span
      className={clsx(
        "inline-flex items-center font-medium rounded-full",
        variantClasses[variant],
        sizeClasses[size],
        className,
      )}
    >
      {dot && (
        <span
          className={clsx(
            "w-1.5 h-1.5 rounded-full mr-1.5",
            dotColors[variant],
          )}
        />
      )}
      {children}
    </span>
  );
}

// Pre-built status badges
export function StatusBadge({
  status,
}: {
  status:
    | "active"
    | "inactive"
    | "suspended"
    | "pending"
    | "running"
    | "stopped"
    | "error";
}) {
  const statusConfig = {
    active: { variant: "success" as const, label: "Active" },
    inactive: { variant: "default" as const, label: "Inactive" },
    suspended: { variant: "danger" as const, label: "Suspended" },
    pending: { variant: "warning" as const, label: "Pending" },
    running: { variant: "success" as const, label: "Running" },
    stopped: { variant: "danger" as const, label: "Stopped" },
    error: { variant: "danger" as const, label: "Error" },
  };

  const config = statusConfig[status] || statusConfig.inactive;
  return (
    <Badge variant={config.variant} dot>
      {config.label}
    </Badge>
  );
}
