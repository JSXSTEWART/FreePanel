import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Card, CardHeader, CardBody } from '../Card';

describe('Card', () => {
  it('renders children correctly', () => {
    render(<Card>Card Content</Card>);

    expect(screen.getByText('Card Content')).toBeInTheDocument();
  });

  it('applies card class', () => {
    const { container } = render(<Card>Content</Card>);

    expect(container.firstChild).toHaveClass('card');
  });

  it('applies custom className', () => {
    const { container } = render(<Card className="custom-card">Content</Card>);

    expect(container.firstChild).toHaveClass('card', 'custom-card');
  });
});

describe('CardHeader', () => {
  it('renders children correctly', () => {
    render(<CardHeader>Header Title</CardHeader>);

    expect(screen.getByText('Header Title')).toBeInTheDocument();
  });

  it('applies card-header class', () => {
    const { container } = render(<CardHeader>Header</CardHeader>);

    expect(container.firstChild).toHaveClass('card-header');
  });

  it('renders action slot when provided', () => {
    render(
      <CardHeader action={<button>Action</button>}>Header Title</CardHeader>
    );

    expect(screen.getByText('Header Title')).toBeInTheDocument();
    expect(screen.getByRole('button')).toHaveTextContent('Action');
  });

  it('does not render action slot when not provided', () => {
    render(<CardHeader>Header Title</CardHeader>);

    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('applies custom className', () => {
    const { container } = render(
      <CardHeader className="custom-header">Header</CardHeader>
    );

    expect(container.firstChild).toHaveClass('card-header', 'custom-header');
  });

  it('has flex layout for header and action alignment', () => {
    const { container } = render(<CardHeader>Header</CardHeader>);

    expect(container.firstChild).toHaveClass('flex', 'items-center', 'justify-between');
  });
});

describe('CardBody', () => {
  it('renders children correctly', () => {
    render(<CardBody>Body Content</CardBody>);

    expect(screen.getByText('Body Content')).toBeInTheDocument();
  });

  it('applies card-body class', () => {
    const { container } = render(<CardBody>Body</CardBody>);

    expect(container.firstChild).toHaveClass('card-body');
  });

  it('applies custom className', () => {
    const { container } = render(
      <CardBody className="custom-body">Body</CardBody>
    );

    expect(container.firstChild).toHaveClass('card-body', 'custom-body');
  });
});

describe('Card composition', () => {
  it('renders complete card structure', () => {
    render(
      <Card>
        <CardHeader action={<button>Edit</button>}>User Profile</CardHeader>
        <CardBody>
          <p>Name: John Doe</p>
          <p>Email: john@example.com</p>
        </CardBody>
      </Card>
    );

    expect(screen.getByText('User Profile')).toBeInTheDocument();
    expect(screen.getByText('Name: John Doe')).toBeInTheDocument();
    expect(screen.getByText('Email: john@example.com')).toBeInTheDocument();
    expect(screen.getByRole('button')).toHaveTextContent('Edit');
  });
});
