import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import Button from '../Button';

describe('Button', () => {
  it('renders children correctly', () => {
    render(<Button>Click me</Button>);

    expect(screen.getByRole('button')).toHaveTextContent('Click me');
  });

  it('applies primary variant by default', () => {
    render(<Button>Primary</Button>);

    expect(screen.getByRole('button')).toHaveClass('btn-primary');
  });

  it('applies secondary variant when specified', () => {
    render(<Button variant="secondary">Secondary</Button>);

    expect(screen.getByRole('button')).toHaveClass('btn-secondary');
  });

  it('applies danger variant when specified', () => {
    render(<Button variant="danger">Danger</Button>);

    expect(screen.getByRole('button')).toHaveClass('btn-danger');
  });

  it('applies success variant when specified', () => {
    render(<Button variant="success">Success</Button>);

    expect(screen.getByRole('button')).toHaveClass('btn-success');
  });

  it('applies medium size by default', () => {
    render(<Button>Medium</Button>);

    expect(screen.getByRole('button')).toHaveClass('px-4', 'py-2', 'text-sm');
  });

  it('applies small size when specified', () => {
    render(<Button size="sm">Small</Button>);

    expect(screen.getByRole('button')).toHaveClass('px-3', 'py-1.5', 'text-xs');
  });

  it('applies large size when specified', () => {
    render(<Button size="lg">Large</Button>);

    expect(screen.getByRole('button')).toHaveClass('px-6', 'py-3', 'text-base');
  });

  it('handles click events', () => {
    const handleClick = vi.fn();
    render(<Button onClick={handleClick}>Click me</Button>);

    fireEvent.click(screen.getByRole('button'));

    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it('disables the button when disabled prop is true', () => {
    render(<Button disabled>Disabled</Button>);

    expect(screen.getByRole('button')).toBeDisabled();
  });

  it('disables the button when isLoading is true', () => {
    render(<Button isLoading>Loading</Button>);

    expect(screen.getByRole('button')).toBeDisabled();
  });

  it('shows loading spinner when isLoading is true', () => {
    render(<Button isLoading>Submit</Button>);

    expect(screen.getByRole('button')).toHaveTextContent('Loading...');
    expect(screen.getByRole('button').querySelector('svg')).toBeInTheDocument();
  });

  it('does not show loading spinner when isLoading is false', () => {
    render(<Button isLoading={false}>Submit</Button>);

    expect(screen.getByRole('button')).not.toHaveTextContent('Loading...');
    expect(screen.getByRole('button')).toHaveTextContent('Submit');
  });

  it('applies custom className', () => {
    render(<Button className="custom-class">Custom</Button>);

    expect(screen.getByRole('button')).toHaveClass('custom-class');
  });

  it('passes through additional HTML button attributes', () => {
    render(
      <Button type="submit" name="submitBtn">
        Submit
      </Button>
    );

    const button = screen.getByRole('button');
    expect(button).toHaveAttribute('type', 'submit');
    expect(button).toHaveAttribute('name', 'submitBtn');
  });

  it('does not fire click handler when disabled', () => {
    const handleClick = vi.fn();
    render(
      <Button onClick={handleClick} disabled>
        Disabled
      </Button>
    );

    fireEvent.click(screen.getByRole('button'));

    expect(handleClick).not.toHaveBeenCalled();
  });

  it('does not fire click handler when loading', () => {
    const handleClick = vi.fn();
    render(
      <Button onClick={handleClick} isLoading>
        Loading
      </Button>
    );

    fireEvent.click(screen.getByRole('button'));

    expect(handleClick).not.toHaveBeenCalled();
  });
});
