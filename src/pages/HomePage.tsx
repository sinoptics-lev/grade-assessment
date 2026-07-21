import { useState } from 'react';
import { useNavigate } from 'react-router';
import { apiRequest } from '@/hooks/useApi';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Brain, Target, ClipboardCheck, TrendingUp, Shield, Users } from 'lucide-react';

export default function HomePage() {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [formData, setFormData] = useState({
    full_name: '', email: '', position: '', department: '', experience_years: ''
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    try {
      const response = await apiRequest('assessment', 'POST', {
        ...formData, experience_years: parseInt(formData.experience_years) || 0
      });
      const resp = response as Record<string, unknown>;
      if (resp.success === true && resp.assessment_id) {
        sessionStorage.setItem('assessment_id', resp.assessment_id.toString());
        sessionStorage.setItem('access_token', resp.access_token as string);
        sessionStorage.setItem('current_question', '1');
        sessionStorage.setItem('total_questions', '15');
        sessionStorage.setItem('assessment_data', JSON.stringify(formData));
        navigate('/assessment');
      } else {
        setError((resp.message as string) || 'Ошибка');
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Ошибка сервера');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
      <header className="bg-white border-b shadow-sm">
        <div className="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-primary rounded-lg">
              <Brain className="w-6 h-6 text-white" />
            </div>
            <div>
              <h1 className="text-xl font-bold text-slate-900">Grade Assessment</h1>
              <p className="text-xs text-slate-500">Интерактивная оценка грейда сотрудников</p>
            </div>
          </div>
          <Button variant="outline" size="sm" onClick={() => navigate('/admin')}>
            <Shield className="w-4 h-4 mr-2" />Админ-панель
          </Button>
        </div>
      </header>

      <div className="max-w-6xl mx-auto px-4 py-8">
        <div className="grid lg:grid-cols-2 gap-8 items-start">
          <div className="space-y-6">
            <div>
              <Badge variant="secondary" className="mb-4">MVP v1.0</Badge>
              <h2 className="text-3xl font-bold text-slate-900 mb-4">AI-оценка компетенций аналитиков</h2>
              <p className="text-lg text-slate-600 leading-relaxed">
                Адаптивная система оценки hard и soft skills. Вопросы подстраиваются под ваш уровень в реальном времени.
              </p>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <Card><CardContent className="pt-6">
                <Target className="w-8 h-8 text-primary mb-3" />
                <h3 className="font-semibold mb-1">Адаптивные вопросы</h3>
                <p className="text-sm text-slate-500">AI генерирует вопросы на основе ответов</p>
              </CardContent></Card>
              <Card><CardContent className="pt-6">
                <ClipboardCheck className="w-8 h-8 text-primary mb-3" />
                <h3 className="font-semibold mb-1">Полный отчёт</h3>
                <p className="text-sm text-slate-500">Матрица навыков и план развития</p>
              </CardContent></Card>
              <Card><CardContent className="pt-6">
                <TrendingUp className="w-8 h-8 text-primary mb-3" />
                <h3 className="font-semibold mb-1">Объективная оценка</h3>
                <p className="text-sm text-slate-500">AI оценивает выбранные ответы</p>
              </CardContent></Card>
              <Card><CardContent className="pt-6">
                <Users className="w-8 h-8 text-primary mb-3" />
                <h3 className="font-semibold mb-1">Hard & Soft Skills</h3>
                <p className="text-sm text-slate-500">Технические и личностные компетенции</p>
              </CardContent></Card>
            </div>
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <h4 className="font-semibold text-blue-900 mb-2">Как это работает?</h4>
              <ol className="space-y-2 text-sm text-blue-800">
                <li>1. Заполните форму — укажите позицию и опыт</li>
                <li>2. Выбирайте варианты ответов — вопросы адаптируются под уровень</li>
                <li>3. Получите детальный отчёт с рекомендациями</li>
              </ol>
            </div>
          </div>

          <Card className="shadow-lg">
            <CardHeader>
              <CardTitle>Начать оценку</CardTitle>
              <CardDescription>Заполните информацию для персонализации вопросов</CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleSubmit} className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="full_name">ФИО *</Label>
                  <Input id="full_name" placeholder="Иванов Иван Иванович"
                    value={formData.full_name} onChange={(e) => setFormData({ ...formData, full_name: e.target.value })} required />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="email">Email</Label>
                  <Input id="email" type="email" placeholder="ivan@company.ru"
                    value={formData.email} onChange={(e) => setFormData({ ...formData, email: e.target.value })} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="position">Позиция *</Label>
                  <Select value={formData.position} onValueChange={(v) => setFormData({ ...formData, position: v })}>
                    <SelectTrigger><SelectValue placeholder="Выберите позицию" /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="Бизнес-аналитик">Бизнес-аналитик</SelectItem>
                      <SelectItem value="Системный аналитик">Системный аналитик</SelectItem>
                      <SelectItem value="Ведущий бизнес-аналитик">Ведущий бизнес-аналитик</SelectItem>
                      <SelectItem value="Ведущий системный аналитик">Ведущий системный аналитик</SelectItem>
                      <SelectItem value="Product Owner">Product Owner</SelectItem>
                      <SelectItem value="Системный архитектор">Системный архитектор</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="department">Департамент</Label>
                  <Input id="department" placeholder="Департамент цифровизации"
                    value={formData.department} onChange={(e) => setFormData({ ...formData, department: e.target.value })} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="experience">Опыт работы (лет)</Label>
                  <Input id="experience" type="number" min="0" max="50" placeholder="3"
                    value={formData.experience_years} onChange={(e) => setFormData({ ...formData, experience_years: e.target.value })} />
                </div>
                {error && <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{error}</div>}
                <Button type="submit" className="w-full" size="lg" disabled={loading}>
                  {loading ? <><span className="animate-spin mr-2">◌</span>Создание...</> : <><Brain className="w-5 h-5 mr-2" />Начать оценку</>}
                </Button>
                <p className="text-xs text-center text-slate-500">~15 вопросов, 20-30 минут</p>
              </form>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
