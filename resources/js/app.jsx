import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import AppErrorBoundary from './components/AppErrorBoundary';
import AppLayout from './components/AppLayout';
import DashboardPage from './pages/DashboardPage';
import LoginPage from './pages/LoginPage';
import ResultsPage from './pages/ResultsPage';
import RolesPage from './pages/RolesPage';
import ScenariosPage from './pages/ScenariosPage';
import UsersPage from './pages/UsersPage';

function ShellRoute({ children }) {
    return <AppLayout>{children}</AppLayout>;
}

function App() {
    return (
        <BrowserRouter>
            <Routes>
                <Route path="/login" element={<LoginPage />} />
                <Route path="/dashboard" element={<ShellRoute><DashboardPage /></ShellRoute>} />
                <Route path="/escenarios" element={<ShellRoute><ScenariosPage /></ShellRoute>} />
                <Route path="/resultados" element={<ShellRoute><ResultsPage /></ShellRoute>} />
                <Route path="/users-list" element={<ShellRoute><UsersPage /></ShellRoute>} />
                <Route path="/users-create" element={<ShellRoute><UsersPage createOnLoad /></ShellRoute>} />
                <Route path="/users/:id" element={<ShellRoute><UsersPage /></ShellRoute>} />
                <Route path="/roles-list" element={<ShellRoute><RolesPage /></ShellRoute>} />
                <Route path="/roles-create" element={<ShellRoute><RolesPage createOnLoad /></ShellRoute>} />
                <Route path="/roles/:id" element={<ShellRoute><RolesPage /></ShellRoute>} />
                <Route path="*" element={<Navigate to="/dashboard" replace />} />
            </Routes>
        </BrowserRouter>
    );
}

const rootElement = document.getElementById('app');

if (!rootElement) {
    throw new Error('No se encontró el elemento raíz #app.');
}

createRoot(rootElement).render(
    <StrictMode>
        <AppErrorBoundary>
            <App />
        </AppErrorBoundary>
    </StrictMode>,
);
