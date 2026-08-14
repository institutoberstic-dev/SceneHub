import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import AppLayout from './components/AppLayout';
import DashboardPage from './pages/DashboardPage';
import LoginPage from './pages/LoginPage';
import PlaceholderPage from './pages/PlaceholderPage';
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
                <Route path="/versiones" element={<ShellRoute><PlaceholderPage type="versions" /></ShellRoute>} />
                <Route path="/sincronizacion" element={<ShellRoute><PlaceholderPage type="sync" /></ShellRoute>} />
                <Route path="/cache-local" element={<ShellRoute><PlaceholderPage type="cache" /></ShellRoute>} />
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

createRoot(document.getElementById('app')).render(
    <StrictMode>
        <App />
    </StrictMode>,
);
