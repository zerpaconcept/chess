import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 32 32"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path
                fill="currentColor"
                fillOpacity="0.22"
                d="M4 4h12v12H4V4Zm16 0h12v12H20V4ZM4 20h12v12H4V20Zm16 0h12v12H20V20Z"
            />
            <path
                fill="currentColor"
                fillOpacity="0.38"
                d="M16 4h12v12H16V4ZM4 16h12v12H4V16Z"
            />
            <path
                fill="currentColor"
                d="M21.5 24.5h-7v-1.6c0-2.1 1-3.8 2.4-5.1-1.2-.7-2-2-2-3.5 0-2.2 1.8-4 4.1-4s4.1 1.8 4.1 4c0 1.5-.8 2.8-2 3.5 1.4 1.3 2.4 3 2.4 5.1v1.6Zm-3.5-13.2a1.8 1.8 0 1 0 0 3.6 1.8 1.8 0 0 0 0-3.6Z"
            />
            <path
                fill="currentColor"
                fillOpacity="0.55"
                d="M10 25.5h14v1.5H10v-1.5Z"
            />
        </svg>
    );
}
