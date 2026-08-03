import { TestBed } from '@angular/core/testing';
import { provideAnimationsAsync } from '@angular/platform-browser/animations/async';
import { of } from 'rxjs';
import { UnifiedPersonListModalComponent } from './unified-person-list-modal.component';
import { UnifiedPersonService } from '../../data-access/services/unified-person.service';

describe('UnifiedPersonListModalComponent', () => {
  beforeEach(async () => {
    // Mock explícito del servicio para evitar solicitar GLOBAL_API_URL e HttpClient
    const mockUnifiedPersonService = {
      searchPersons: () => of([]),
      getPersons: () => of({ data: [], meta: {} }),
    };

    await TestBed.configureTestingModule({
      imports: [UnifiedPersonListModalComponent],
      providers: [
        provideAnimationsAsync(),
        {
          provide: UnifiedPersonService,
          useValue: mockUnifiedPersonService,
        },
      ],
    }).compileComponents();
  });

  it('should create', () => {
    const fixture = TestBed.createComponent(UnifiedPersonListModalComponent);
    const component = fixture.componentInstance;
    expect(component).toBeTruthy();
  });
});